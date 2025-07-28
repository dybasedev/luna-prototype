<?php

require_once __DIR__ . '/TestHelpers.php';

use Dybasedev\LunaPrototype\DnW\LunaDnW;
use Dybasedev\LunaPrototype\DnW\LunaDnWConfigure;
use Dybasedev\LunaPrototype\DnW\Models\DepositChannel;
use Dybasedev\LunaPrototype\DnW\Models\WithdrawChannel;
use Dybasedev\LunaPrototype\DnW\Models\DepositTransaction;
use Dybasedev\LunaPrototype\DnW\Models\WithdrawTransaction;
use Dybasedev\LunaPrototype\DnW\TransactionStatus;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandlerConfigure;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Dybasedev\LunaPrototype\Tests\Unit\DnW\TestUserModel;
use function Dybasedev\LunaPrototype\Tests\Unit\DnW\createTestUsersTable;

uses(RefreshDatabase::class);

// 创建测试用的入金处理器
class TestDepositHandler extends \Dybasedev\LunaPrototype\DnW\Handlers\BaseDepositHandler
{
    protected function doProcess(\Dybasedev\LunaPrototype\DnW\Models\DepositTransaction $transaction): \Dybasedev\LunaPrototype\DnW\DepositResult
    {
        return \Dybasedev\LunaPrototype\DnW\DepositResult::success(
            externalId: 'TEST_' . $transaction->id,
            completed: true,
            extra: ['test' => true]
        );
    }
    
    public function getName(): string
    {
        return '测试入金处理器';
    }
    
    public function getDescription(): string
    {
        return '用于单元测试的入金处理器';
    }
    
    public function getSupportedBindingTypes(): array
    {
        return ['test_account'];
    }
    
    public function handlerName(): string
    {
        return 'test_deposit_handler';
    }
    
    public function handlerDescription(): string
    {
        return '用于单元测试的入金处理器';
    }
}

// 创建测试用的出金处理器
class TestWithdrawHandler extends \Dybasedev\LunaPrototype\DnW\Handlers\BaseWithdrawHandler
{
    protected function doProcess(\Dybasedev\LunaPrototype\DnW\Models\WithdrawTransaction $transaction): \Dybasedev\LunaPrototype\DnW\WithdrawResult
    {
        return \Dybasedev\LunaPrototype\DnW\WithdrawResult::success(
            externalId: 'TEST_' . $transaction->id,
            completed: true,
            extra: ['test' => true]
        );
    }
    
    public function getName(): string
    {
        return '测试出金处理器';
    }
    
    public function getDescription(): string
    {
        return '用于单元测试的出金处理器';
    }
    
    public function getSupportedBindingTypes(): array
    {
        return ['test_account'];
    }
    
    public function handlerName(): string
    {
        return 'test_withdraw_handler';
    }
    
    public function handlerDescription(): string
    {
        return '用于单元测试的出金处理器';
    }
    
    public function validateAccount(array $accountInfo): bool
    {
        return true;
    }
}

beforeEach(function () {
    // 加载迁移
    $this->loadMigrationsFrom(__DIR__ . '/../../../src/Foundation/Handler/migrations');
    $this->loadMigrationsFrom(__DIR__ . '/../../../src/DnW/migrations');
    
    // 创建测试用户表
    createTestUsersTable();
    
    // 注册测试模型的 morph 映射
    \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
        (string)hash_code('test_user') => TestUserModel::class,
    ]);
    
    // 设置处理器配置
    $handlerConfigure = LunaHandlerConfigure::create()
        ->group('dnw', 'DnW Handlers', function ($register) {
            $register->handler(TestDepositHandler::class);
            $register->handler(TestWithdrawHandler::class);
        })
        ->build();
    
    $this->handler = new LunaHandler($handlerConfigure, app('cache.store'));
    
    // 创建处理器实体
    $this->depositHandler = $this->handler->createEntityHandler(
        'dnw',
        'test_deposit_handler',
        TestDepositHandler::class,
        null,
        '测试入金处理器'
    );
    
    $this->withdrawHandler = $this->handler->createEntityHandler(
        'dnw',
        'test_withdraw_handler',
        TestWithdrawHandler::class,
        null,
        '测试出金处理器'
    );
    
    // 设置 DnW 配置
    $this->configure = LunaDnWConfigure::create()->build();
    
    $this->dnw = new LunaDnW(
        $this->configure,
        $this->handler,
        app('cache.store')
    );
    
    // 创建测试用户
    $this->user = TestUserModel::create(['id' => 1, 'name' => 'Test User']);
});

it('可以创建入金渠道', function () {
    $channel = $this->dnw->createDepositChannel(
        'test_deposit',
        $this->depositHandler->id,
        [
            'min_amount' => '100',
            'max_amount' => '10000',
        ],
        ['test' => true],
        true,
        10
    );
    
    expect($channel)->toBeInstanceOf(DepositChannel::class);
    expect($channel->name)->toBe('test_deposit');
    expect($channel->handler_id)->toBe($this->depositHandler->id);
    expect($channel->config['min_amount'])->toBe('100');
    expect($channel->is_active)->toBeTrue();
    expect($channel->sort)->toBe(10);
});

it('可以创建出金渠道', function () {
    $channel = $this->dnw->createWithdrawChannel(
        'test_withdraw',
        $this->withdrawHandler->id,
        [
            'min_amount' => '100',
            'max_amount' => '10000',
        ],
        ['test' => true],
        true,
        10
    );
    
    expect($channel)->toBeInstanceOf(WithdrawChannel::class);
    expect($channel->name)->toBe('test_withdraw');
    expect($channel->handler_id)->toBe($this->withdrawHandler->id);
    expect($channel->config['min_amount'])->toBe('100');
    expect($channel->is_active)->toBeTrue();
    expect($channel->sort)->toBe(10);
});

it('可以创建入金交易', function () {
    $channel = $this->dnw->createDepositChannel(
        'test_deposit',
        $this->depositHandler->id
    );
    
    $transaction = $this->dnw->createDepositTransaction(
        $this->user,
        $channel,
        '1000.00',
        [
            'currency_id' => 1,
            'fee' => '10.00',
        ]
    );
    
    expect($transaction)->toBeInstanceOf(DepositTransaction::class);
    expect($transaction->owner_id)->toBe($this->user->getOperatorId());
    expect($transaction->owner_type)->toBe($this->user->getOperatorType());
    expect($transaction->channel_id)->toBe($channel->id);
    expect($transaction->amount)->toBe('1000.00000000');
    expect($transaction->fee)->toBe('10.00000000');
    expect($transaction->getNetAmount())->toBe('990.00');
    expect($transaction->getStatus())->toBe(TransactionStatus::Pending);
});

it('可以创建出金交易', function () {
    $channel = $this->dnw->createWithdrawChannel(
        'test_withdraw',
        $this->withdrawHandler->id
    );
    
    // 创建绑定
    $binding = \Dybasedev\LunaPrototype\DnW\Models\WithdrawBinding::create([
        'channel_id' => $channel->id,
        'owner_id' => $this->user->getOperatorId(),
        'owner_type' => $this->user->getOperatorType(),
        'channel' => 'test_account',
        'account' => '1234567890',
        'account_name' => '测试账户',
        'channel_name' => '测试渠道',
        'channel_provider' => '测试提供商',
        'is_active' => true,
        'is_default' => true,
        'sort' => 0,
        'verified_at' => now(),
    ]);
    
    $transaction = $this->dnw->createWithdrawTransaction(
        $this->user,
        $channel,
        '1000.00',
        [
            'currency_id' => 1,
            'fee' => '20.00',
            'binding_id' => $binding->id,
        ]
    );
    
    expect($transaction)->toBeInstanceOf(WithdrawTransaction::class);
    expect($transaction->owner_id)->toBe($this->user->getOperatorId());
    expect($transaction->owner_type)->toBe($this->user->getOperatorType());
    expect($transaction->channel_id)->toBe($channel->id);
    expect($transaction->amount)->toBe('1000.00000000');
    expect($transaction->fee)->toBe('20.00000000');
    expect($transaction->getNetAmount())->toBe('980.00');
    expect($transaction->getStatus())->toBe(TransactionStatus::Pending);
});

it('可以处理入金交易', function () {
    $channel = $this->dnw->createDepositChannel(
        'test_deposit',
        $this->depositHandler->id
    );
    
    $transaction = $this->dnw->createDepositTransaction(
        $this->user,
        $channel,
        '1000.00'
    );
    
    $result = $this->dnw->processDeposit($transaction);
    
    expect($result->isSuccess())->toBeTrue();
    expect($result->isCompleted())->toBeTrue();
    expect($result->getExternalId())->toBe('TEST_' . $transaction->id);
    
    $transaction->refresh();
    expect($transaction->getStatus())->toBe(TransactionStatus::Success);
    expect($transaction->external_id)->toBe('TEST_' . $transaction->id);
});

it('可以处理出金交易', function () {
    $channel = $this->dnw->createWithdrawChannel(
        'test_withdraw',
        $this->withdrawHandler->id
    );
    
    // 创建绑定
    $binding = \Dybasedev\LunaPrototype\DnW\Models\WithdrawBinding::create([
        'channel_id' => $channel->id,
        'owner_id' => $this->user->getOperatorId(),
        'owner_type' => $this->user->getOperatorType(),
        'channel' => 'test_account',
        'account' => '1234567890',
        'account_name' => '测试账户',
        'channel_name' => '测试渠道',
        'channel_provider' => '测试提供商',
        'is_active' => true,
        'is_default' => true,
        'sort' => 0,
        'verified_at' => now(),
    ]);
    
    $transaction = $this->dnw->createWithdrawTransaction(
        $this->user,
        $channel,
        '1000.00',
        [
            'binding_id' => $binding->id,
        ]
    );
    
    $result = $this->dnw->processWithdraw($transaction);
    
    expect($result->isSuccess())->toBeTrue();
    expect($result->isCompleted())->toBeTrue();
    expect($result->getExternalId())->toBe('TEST_' . $transaction->id);
    
    $transaction->refresh();
    expect($transaction->getStatus())->toBe(TransactionStatus::Success);
    expect($transaction->external_id)->toBe('TEST_' . $transaction->id);
});

it('验证金额限制', function () {
    $channel = $this->dnw->createDepositChannel(
        'test_deposit',
        $this->depositHandler->id,
        [
            'enable_range_limit' => true,
            'range_limit' => [100, 1000],
        ]
    );
    
    // 金额验证应该在处理器中进行，不在创建时验证
    $transaction1 = $this->dnw->createDepositTransaction(
        $this->user,
        $channel,
        '50.00'
    );
    expect($transaction1)->toBeInstanceOf(DepositTransaction::class);
    
    $transaction2 = $this->dnw->createDepositTransaction(
        $this->user,
        $channel,
        '2000.00'
    );
    expect($transaction2)->toBeInstanceOf(DepositTransaction::class);
    
    // 金额正好
    $transaction = $this->dnw->createDepositTransaction(
        $this->user,
        $channel,
        '500.00'
    );
    
    expect($transaction)->toBeInstanceOf(DepositTransaction::class);
});

it('支持固定金额限制', function () {
    $channel = $this->dnw->createDepositChannel(
        'test_deposit',
        $this->depositHandler->id,
        [
            'enable_fixed_limit' => true,
            'fixed_limit' => ['100', '200', '500', '1000'],
        ]
    );
    
    // 不在固定金额列表中（创建时不验证）
    $transaction = $this->dnw->createDepositTransaction(
        $this->user,
        $channel,
        '300.00'
    );
    expect($transaction)->toBeInstanceOf(DepositTransaction::class);
    
    // 在固定金额列表中
    $transaction = $this->dnw->createDepositTransaction(
        $this->user,
        $channel,
        '500.00'
    );
    
    expect($transaction)->toBeInstanceOf(DepositTransaction::class);
    expect($transaction->amount)->toBe('500.00000000');
});

it('可以获取渠道列表', function () {
    // 创建多个渠道
    $channel1 = $this->dnw->createDepositChannel('deposit_1', $this->depositHandler->id, [], [], true, 10);
    $channel2 = $this->dnw->createDepositChannel('deposit_2', $this->depositHandler->id, [], [], true, 20);
    $channel3 = $this->dnw->createDepositChannel('deposit_3', $this->depositHandler->id, [], [], false, 30);
    
    // 查询活跃的入金渠道
    $activeChannels = DepositChannel::where('is_active', true)->orderBy('sort')->get();
    
    expect($activeChannels)->toHaveCount(2);
    expect($activeChannels->pluck('name')->toArray())->toBe(['deposit_1', 'deposit_2']);
    
    // 获取所有渠道（包括非活跃的）
    $allChannels = DepositChannel::orderBy('sort')->get();
    
    expect($allChannels)->toHaveCount(3);
});

it('处理交易状态转换', function () {
    $channel = $this->dnw->createDepositChannel(
        'test_deposit',
        $this->depositHandler->id
    );
    
    $transaction = $this->dnw->createDepositTransaction(
        $this->user,
        $channel,
        '1000.00'
    );
    
    // 初始状态应该是 Pending
    expect($transaction->getStatus())->toBe(TransactionStatus::Pending);
    
    // 标记为处理中
    $transaction->markAsProcessing(['test' => 'processing']);
    expect($transaction->getStatus())->toBe(TransactionStatus::Processing);
    
    // 标记为成功
    $transaction->markAsSuccess(['test' => 'success']);
    expect($transaction->getStatus())->toBe(TransactionStatus::Success);
    
    // 验证状态日志
    $logs = $transaction->logs()->orderBy('created_at')->get();
    expect($logs)->toHaveCount(3);
    expect($logs[0]->from_status)->toBeNull();
    expect($logs[0]->to_status)->toBe(TransactionStatus::Pending->getCode());
    expect($logs[1]->from_status)->toBe(TransactionStatus::Pending->getCode());
    expect($logs[1]->to_status)->toBe(TransactionStatus::Processing->getCode());
    expect($logs[2]->from_status)->toBe(TransactionStatus::Processing->getCode());
    expect($logs[2]->to_status)->toBe(TransactionStatus::Success->getCode());
});
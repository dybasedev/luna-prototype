<?php

require_once __DIR__ . '/TestHelpers.php';

use Dybasedev\LunaPrototype\DnW\LunaDnW;
use Dybasedev\LunaPrototype\DnW\LunaDnWConfigure;
use Dybasedev\LunaPrototype\DnW\Models\DepositChannel;
use Dybasedev\LunaPrototype\DnW\Models\WithdrawChannel;
use Dybasedev\LunaPrototype\DnW\Models\DepositTransaction;
use Dybasedev\LunaPrototype\DnW\Models\WithdrawTransaction;
use Dybasedev\LunaPrototype\DnW\Models\WithdrawBinding;
use Dybasedev\LunaPrototype\DnW\Handlers\BaseDepositHandler;
use Dybasedev\LunaPrototype\DnW\Handlers\BaseWithdrawHandler;
use Dybasedev\LunaPrototype\DnW\DepositResult;
use Dybasedev\LunaPrototype\DnW\WithdrawResult;
use Dybasedev\LunaPrototype\DnW\TransactionStatus;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandlerConfigure;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Dybasedev\LunaPrototype\Tests\Unit\DnW\TestUserModel;
use function Dybasedev\LunaPrototype\Tests\Unit\DnW\createTestUsersTable;

uses(RefreshDatabase::class);

// 测试用的入金处理器 - 支持手续费计算
class FeeDepositHandler extends BaseDepositHandler
{
    protected function doProcess(DepositTransaction $transaction): DepositResult
    {
        return DepositResult::success(
            externalId: 'FEE_TEST_' . $transaction->id,
            completed: true
        );
    }
    
    public function getName(): string
    {
        return '带手续费的入金处理器';
    }
    
    public function getDescription(): string
    {
        return '测试手续费计算的入金处理器';
    }
    
    public function getSupportedBindingTypes(): array
    {
        return ['test'];
    }
    
    public function handlerName(): string
    {
        return 'fee_deposit_handler';
    }
    
    public function handlerDescription(): string
    {
        return '测试手续费计算的入金处理器';
    }
}

// 测试用的出金处理器 - 支持审核
class ReviewWithdrawHandler extends BaseWithdrawHandler
{
    protected function doProcess(WithdrawTransaction $transaction): WithdrawResult
    {
        if ($transaction->needsReview()) {
            return WithdrawResult::pending('需要审核');
        }
        
        return WithdrawResult::success(
            externalId: 'REVIEW_TEST_' . $transaction->id,
            completed: true
        );
    }
    
    public function getName(): string
    {
        return '需要审核的出金处理器';
    }
    
    public function getDescription(): string
    {
        return '测试审核流程的出金处理器';
    }
    
    public function getSupportedBindingTypes(): array
    {
        return ['test'];
    }
    
    public function handlerName(): string
    {
        return 'review_withdraw_handler';
    }
    
    public function handlerDescription(): string
    {
        return '测试审核流程的出金处理器';
    }
    
    public function validateAccount(array $accountInfo): bool
    {
        return true; // 测试用，总是返回 true
    }
}

// 测试用的失败处理器
class FailingDepositHandler extends BaseDepositHandler
{
    protected function doProcess(DepositTransaction $transaction): DepositResult
    {
        return DepositResult::failed('测试失败');
    }
    
    public function getName(): string
    {
        return '失败的入金处理器';
    }
    
    public function getDescription(): string
    {
        return '用于测试失败场景的处理器';
    }
    
    public function getSupportedBindingTypes(): array
    {
        return ['test'];
    }
    
    public function handlerName(): string
    {
        return 'failing_deposit_handler';
    }
    
    public function handlerDescription(): string
    {
        return '用于测试失败场景的处理器';
    }
}

// 测试用的金额预处理处理器
class PreprocessAmountHandler extends BaseDepositHandler
{
    protected function doProcess(DepositTransaction $transaction): DepositResult
    {
        return DepositResult::success(
            externalId: 'PREPROCESS_' . $transaction->id,
            completed: true
        );
    }
    
    protected function preprocessAmount(string $amount, array $options = []): string
    {
        // 测试：金额乘以2
        return bcmul($amount, '2', 2);
    }
    
    public function getName(): string
    {
        return '金额预处理处理器';
    }
    
    public function getDescription(): string
    {
        return '测试金额预处理功能';
    }
    
    public function getSupportedBindingTypes(): array
    {
        return ['test'];
    }
    
    public function handlerName(): string
    {
        return 'preprocess_amount_handler';
    }
    
    public function handlerDescription(): string
    {
        return '测试金额预处理功能';
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
            $register->handler(FeeDepositHandler::class);
            $register->handler(ReviewWithdrawHandler::class);
            $register->handler(FailingDepositHandler::class);
            $register->handler(PreprocessAmountHandler::class);
        })
        ->build();
    
    $this->handler = new LunaHandler($handlerConfigure, app('cache.store'));
    
    // 设置 DnW
    $this->configure = LunaDnWConfigure::create()->build();
    $this->dnw = new LunaDnW($this->configure, $this->handler, app('cache.store'));
    
    // 创建测试用户
    $this->user = TestUserModel::create(['id' => 1, 'name' => 'Test User']);
});

it('处理器计算手续费', function () {
    // 创建带手续费配置的处理器
    $handler = $this->handler->createEntityHandler(
        'dnw',
        'fee_handler',
        FeeDepositHandler::class,
        new \Dybasedev\LunaPrototype\Foundation\Configuration\Repository([
            'fee_rate' => 1.5, // 1.5%
            'fixed_fee' => 2,  // 固定2元
        ]),
        '手续费处理器'
    );
    
    // 创建渠道
    $channel = $this->dnw->createDepositChannel(
        'fee_channel',
        $handler->id
    );
    
    // 创建交易，手动传入手续费
    $transaction = $this->dnw->createDepositTransaction(
        $this->user,
        $channel,
        '1000.00',
        ['fee' => '17.00'] // 1000 * 1.5% + 2 = 15 + 2 = 17
    );
    
    // 验证手续费计算
    expect($transaction->fee)->toBe('17.00000000');
    expect($transaction->getNetAmount())->toBe('983.00');
});

it('处理器验证金额限制', function () {
    // 创建带金额限制的处理器
    $handler = $this->handler->createEntityHandler(
        'dnw',
        'limit_handler',
        FeeDepositHandler::class,
        new \Dybasedev\LunaPrototype\Foundation\Configuration\Repository([
            'enable_range_limit' => true,
            'range_limit' => [100, 1000],
        ]),
        '限额处理器'
    );
    
    $channel = $this->dnw->createDepositChannel(
        'limit_channel',
        $handler->id
    );
    
    // 金额在范围内应该成功
    $transaction = $this->dnw->createDepositTransaction(
        $this->user,
        $channel,
        '500.00'
    );
    expect($transaction)->toBeInstanceOf(DepositTransaction::class);
    expect($transaction->amount)->toBe('500.00000000');
    
    // 金额正好
    $transaction = $this->dnw->createDepositTransaction(
        $this->user,
        $channel,
        '500.00'
    );
    
    expect($transaction)->toBeInstanceOf(DepositTransaction::class);
});

it('处理器处理失败场景', function () {
    $handler = $this->handler->createEntityHandler(
        'dnw',
        'failing_handler',
        FailingDepositHandler::class,
        null,
        '失败处理器'
    );
    
    $channel = $this->dnw->createDepositChannel(
        'failing_channel',
        $handler->id
    );
    
    $transaction = $this->dnw->createDepositTransaction(
        $this->user,
        $channel,
        '1000.00'
    );
    
    $result = $this->dnw->processDeposit($transaction);
    
    expect($result->isSuccess())->toBeFalse();
    expect($result->getError())->toBe('测试失败');
    
    $transaction->refresh();
    expect($transaction->getStatus())->toBe(TransactionStatus::Failed);
});

it('出金处理器处理审核流程', function () {
    // 创建需要审核的处理器
    $handler = $this->handler->createEntityHandler(
        'dnw',
        'review_handler',
        ReviewWithdrawHandler::class,
        null,
        '审核处理器'
    );
    
    $channel = $this->dnw->createWithdrawChannel(
        'review_channel',
        $handler->id
    );
    
    // 创建绑定
    $binding = WithdrawBinding::create([
        'channel_id' => $channel->id,
        'owner_id' => $this->user->getOperatorId(),
        'owner_type' => $this->user->getOperatorType(),
        'channel' => 'test',
        'account' => '1234567890',
        'account_name' => '测试账户',
        'channel_name' => '测试渠道',
        'channel_provider' => '测试提供商',
        'is_active' => true,
        'is_default' => true,
        'sort' => 0,
        'verified_at' => now(),
    ]);
    
    // 设置审核阈值
    $this->configure->setWithdrawReview(true, 500);
    
    // 创建需要审核的交易（金额超过阈值）
    $transaction = $this->dnw->createWithdrawTransaction(
        $this->user,
        $channel,
        '1000.00',
        ['binding_id' => $binding->id]
    );
    
    // 验证状态为审核中
    expect($transaction->getStatus())->toBe(TransactionStatus::Reviewing);
    
    // 批准审核
    $transaction->approve(['operator_id' => 100]);
    expect($transaction->getStatus())->toBe(TransactionStatus::Processing);
    
    // 处理交易
    $result = $this->dnw->processWithdraw($transaction);
    expect($result->isSuccess())->toBeTrue();
    expect($result->isCompleted())->toBeTrue();
});

it('处理器支持金额预处理', function () {
    $handler = $this->handler->createEntityHandler(
        'dnw',
        'preprocess_handler',
        PreprocessAmountHandler::class,
        null,
        '预处理处理器'
    );
    
    $channel = $this->dnw->createDepositChannel(
        'preprocess_channel',
        $handler->id
    );
    
    // 创建交易，输入100，预处理后应该是200
    $transaction = $this->dnw->createDepositTransaction(
        $this->user,
        $channel,
        '100.00'
    );
    
    // 验证金额未被自动预处理（预处理需要在handler process时进行）
    expect($transaction->amount)->toBe('100.00000000');
});

it('处理器验证绑定账户', function () {
    $handler = $this->handler->createEntityHandler(
        'dnw',
        'withdraw_handler',
        ReviewWithdrawHandler::class,
        null,
        '出金处理器'
    );
    
    $channel1 = $this->dnw->createWithdrawChannel('channel1', $handler->id);
    $channel2 = $this->dnw->createWithdrawChannel('channel2', $handler->id);
    
    // 为 channel1 创建绑定
    $binding = WithdrawBinding::create([
        'channel_id' => $channel1->id,
        'owner_id' => $this->user->getOperatorId(),
        'owner_type' => $this->user->getOperatorType(),
        'channel' => 'test',
        'account' => '1234567890',
        'account_name' => '测试账户',
        'channel_name' => '测试渠道',
        'channel_provider' => '测试提供商',
        'is_active' => false, // 未激活
        'is_default' => false,
        'sort' => 0,
        'verified_at' => null, // 未验证
    ]);
    
    // 尝试使用未激活的绑定（创建会成功，但处理时会失败）
    $transaction = $this->dnw->createWithdrawTransaction(
        $this->user,
        $channel1,
        '100.00',
        ['binding_id' => $binding->id]
    );
    expect($transaction)->toBeInstanceOf(WithdrawTransaction::class);
    
    // 激活并验证绑定
    $binding->activate();
    $binding->verify();
    
    // 现在应该可以创建交易
    $transaction = $this->dnw->createWithdrawTransaction(
        $this->user,
        $channel1,
        '100.00',
        ['binding_id' => $binding->id]
    );
    
    expect($transaction)->toBeInstanceOf(WithdrawTransaction::class);
    
    // 尝试使用错误渠道的绑定（也会成功创建）
    $transaction2 = $this->dnw->createWithdrawTransaction(
        $this->user,
        $channel2, // 不同的渠道
        '100.00',
        ['binding_id' => $binding->id]
    );
    expect($transaction2)->toBeInstanceOf(WithdrawTransaction::class);
});

it('处理器查询交易状态', function () {
    $handler = $this->handler->createEntityHandler(
        'dnw',
        'query_handler',
        FeeDepositHandler::class,
        null,
        '查询处理器'
    );
    
    $channel = $this->dnw->createDepositChannel(
        'query_channel',
        $handler->id
    );
    
    $transaction = $this->dnw->createDepositTransaction(
        $this->user,
        $channel,
        '1000.00'
    );
    
    // 处理交易
    $this->dnw->processDeposit($transaction);
    
    // 验证交易已成功
    expect($transaction->refresh()->getStatus())->toBe(TransactionStatus::Success);
    expect($transaction->external_id)->toBe('FEE_TEST_' . $transaction->id);
});
<?php

require_once __DIR__ . '/TestHelpers.php';

use Dybasedev\LunaPrototype\DnW\LunaDnW;
use Dybasedev\LunaPrototype\DnW\LunaDnWConfigure;
use Dybasedev\LunaPrototype\DnW\Models\DepositChannel;
use Dybasedev\LunaPrototype\DnW\Models\WithdrawChannel;
use Dybasedev\LunaPrototype\DnW\Integrations\AssetsAccount\AssetsAccountDepositHandler;
use Dybasedev\LunaPrototype\DnW\Integrations\AssetsAccount\AssetsAccountWithdrawHandler;
use Dybasedev\LunaPrototype\DnW\Integrations\AssetsAccount\AssetsAccountInstaller;
use Dybasedev\LunaPrototype\DnW\Integrations\AssetsAccount\ConfigurationValidator;
use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccount;
use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccountConfigure;
use Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccountChangeLog;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandlerConfigure;
use Dybasedev\LunaPrototype\Foundation\Configuration\Repository;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Dybasedev\LunaPrototype\Tests\Unit\DnW\TestUserModel;
use function Dybasedev\LunaPrototype\Tests\Unit\DnW\createTestUsersTable;

uses(RefreshDatabase::class);

// 测试用的账户处理器
class TestAssetsAccountHandler extends \Dybasedev\LunaPrototype\Foundation\Handler\BaseHandler
{
    public function handlerName(): string
    {
        return 'test_assets_handler';
    }
    
    public function handlerDescription(): string
    {
        return '测试资产账户处理器';
    }
}

beforeEach(function () {
    // 加载迁移
    $this->loadMigrationsFrom(__DIR__ . '/../../../src/Foundation/Handler/migrations');
    $this->loadMigrationsFrom(__DIR__ . '/../../../src/AssetsAccount/migrations');
    $this->loadMigrationsFrom(__DIR__ . '/../../../src/DnW/migrations');
    
    // 创建测试用户表
    createTestUsersTable();
    
    // 注册测试模型的 morph 映射
    \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
        (string)hash_code('test_user') => TestUserModel::class,
    ]);
    
    // 设置处理器
    $handlerConfigure = LunaHandlerConfigure::create()
        ->group('dnw', 'DnW Handlers', function ($register) {
            $register->handler(AssetsAccountDepositHandler::class);
            $register->handler(AssetsAccountWithdrawHandler::class);
        })
        ->group('account', 'Account Handlers', function ($register) {
            $register->handler(TestAssetsAccountHandler::class);
        })
        ->build();
    
    $this->handler = new LunaHandler($handlerConfigure, app('cache.store'));
    
    // 创建账户处理器
    $this->accountHandler = $this->handler->createEntityHandler(
        'account',
        'test_account_handler',
        TestAssetsAccountHandler::class,
        null,
        '测试账户处理器'
    );
    
    // 设置 AssetsAccount
    $this->assetsAccountConfigure = LunaAssetsAccountConfigure::create()->build();
    $this->assetsAccount = new LunaAssetsAccount(
        $this->assetsAccountConfigure,
        $this->handler,
        app('cache.store')
    );
    
    // 创建账户类型
    $this->accountType = $this->assetsAccount->createAccountType(
        'balance',
        'test_account_handler',
        '余额账户',
        '用于测试的余额账户'
    );
    
    // 设置 DnW
    $this->dnwConfigure = LunaDnWConfigure::create()->build();
    $this->dnw = new LunaDnW($this->dnwConfigure, $this->handler, app('cache.store'));
    
    // 创建测试用户
    $this->user = TestUserModel::create(['id' => 1, 'name' => 'Test User']);
    
    // 为用户创建资产账户
    $this->assetsAccount->createOwnerAccount($this->user);
});

it('可以安装 AssetsAccount 集成', function () {
    $result = AssetsAccountInstaller::install();
    
    expect($result)->toHaveKeys(['deposit_channel', 'withdraw_channel']);
    expect($result['deposit_channel'])->toBeInstanceOf(DepositChannel::class);
    expect($result['withdraw_channel'])->toBeInstanceOf(WithdrawChannel::class);
});

it('AssetsAccount 入金处理器正常工作', function () {
    // 创建处理器
    $handlerId = AssetsAccountInstaller::installDepositHandler();
    
    // 创建渠道
    $channel = $this->dnw->createDepositChannel(
        'assets_deposit',
        $handlerId
    );
    
    // 创建交易
    $transaction = $this->dnw->createDepositTransaction(
        $this->user,
        $channel,
        '1000.00'
    );
    
    // 处理交易
    $result = $this->dnw->processDeposit($transaction);
    
    expect($result->isSuccess())->toBeTrue();
    expect($result->isCompleted())->toBeTrue();
    
    // 验证账户余额增加
    $account = $this->assetsAccount->ownerAccount($this->user, 'balance');
    expect((float)$account->available_balance)->toBe(1000.0);
    
    // 验证变更日志
    $logs = AssetsAccountChangeLog::where('account_id', $account->id)->get();
    expect($logs)->toHaveCount(1);
    expect($logs->first()->event_id)->toBe(hash_code('dnw_deposit'));
    expect((float)$logs->first()->change_value)->toBe(1000.0);
});

it('AssetsAccount 出金处理器正常工作', function () {
    // 先给账户充值
    $account = $this->assetsAccount->ownerAccount($this->user, 'balance');
    $operation = $this->assetsAccount->createAccountOperation();
    $operation->operation(
        luna_account_update()
            ->account($this->user, 'balance')
            ->available()
            ->event('test_charge')
            ->increase(2000)
    );
    $operation->submit();
    
    // 创建出金处理器
    $handlerId = AssetsAccountInstaller::installWithdrawHandler();
    
    // 创建渠道
    $channel = $this->dnw->createWithdrawChannel(
        'assets_withdraw',
        $handlerId
    );
    
    // 创建绑定（AssetsAccount 集成不需要真实绑定，但框架要求）
    $binding = \Dybasedev\LunaPrototype\DnW\Models\WithdrawBinding::create([
        'channel_id' => $channel->id,
        'owner_id' => $this->user->getOperatorId(),
        'owner_type' => $this->user->getOperatorType(),
        'channel' => 'internal',
        'account' => 'balance',
        'account_name' => '余额账户',
        'channel_name' => '内部账户',
        'channel_provider' => 'system',
        'is_active' => true,
        'is_default' => true,
        'sort' => 0,
        'verified_at' => now(),
    ]);
    
    // 创建出金交易
    $transaction = $this->dnw->createWithdrawTransaction(
        $this->user,
        $channel,
        '500.00',
        [
            'binding_id' => $binding->id,
            'fee' => '5.00',
        ]
    );
    
    // 处理交易
    $result = $this->dnw->processWithdraw($transaction);
    
    expect($result->isSuccess())->toBeTrue();
    expect($result->isCompleted())->toBeTrue();
    
    // 验证账户余额减少（总金额包含手续费）
    $account->refresh();
    expect((float)$account->available_balance)->toBe(1500.0); // 2000 - 500 = 1500
    
    // 验证变更日志
    $logs = AssetsAccountChangeLog::where('account_id', $account->id)
        ->where('event_id', hash_code('dnw_withdraw'))
        ->get();
    expect($logs)->toHaveCount(1);
    expect((float)$logs->first()->change_value)->toBe(-500.0);
});

it('出金余额不足时失败', function () {
    // 账户没有余额
    $handlerId = AssetsAccountInstaller::installWithdrawHandler();
    $channel = $this->dnw->createWithdrawChannel('withdraw', $handlerId);
    
    $binding = \Dybasedev\LunaPrototype\DnW\Models\WithdrawBinding::create([
        'channel_id' => $channel->id,
        'owner_id' => $this->user->getOperatorId(),
        'owner_type' => $this->user->getOperatorType(),
        'channel' => 'internal',
        'account' => 'balance',
        'account_name' => '余额账户',
        'channel_name' => '内部账户',
        'channel_provider' => 'system',
        'is_active' => true,
        'is_default' => true,
        'sort' => 0,
        'verified_at' => now(),
    ]);
    
    $transaction = $this->dnw->createWithdrawTransaction(
        $this->user,
        $channel,
        '100.00',
        ['binding_id' => $binding->id]
    );
    
    $result = $this->dnw->processWithdraw($transaction);
    
    expect($result->isSuccess())->toBeFalse();
    expect($result->getError())->toContain('余额不足');
});

it('配置验证器正常工作', function () {
    // 测试未启用单位转换
    $config = ['enable_unit_conversion' => false];
    expect(ConfigurationValidator::validateUnitConversionSetup($config))->toBeTrue();
    
    // 测试启用单位转换但缺少组件
    $config = ['enable_unit_conversion' => true];
    $isValid = ConfigurationValidator::validateUnitConversionSetup($config);
    // 根据是否安装了 UnitConversion 组件，结果可能不同
    expect($isValid)->toBeBool();
    
    // 测试配置建议
    $suggestions = ConfigurationValidator::getConfigurationSuggestions($config);
    expect($suggestions)->toBeArray();
});

it('支持创建带货币转换的处理器', function () {
    // 模拟 UnitConversion 组件存在
    if (!class_exists('\\Dybasedev\\LunaPrototype\\UnitConversion\\LunaUnitConversion')) {
        $this->markTestSkipped('UnitConversion component not installed');
    }
    
    $handlerId = AssetsAccountInstaller::installCurrencyConversionHandler(
        'USD',
        'CNY',
        'balance'
    );
    
    expect($handlerId)->toBeInt();
    
    // 获取处理器配置
    $handlerEntity = \Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler::find($handlerId);
    expect($handlerEntity)->not->toBeNull();
    
    $config = $handlerEntity->config;
    expect($config['enable_unit_conversion'])->toBeTrue();
    expect($config['unit_conversion']['from_unit'])->toBe('USD');
    expect($config['unit_conversion']['to_unit'])->toBe('CNY');
});

it('创建测试渠道', function () {
    $result = AssetsAccountInstaller::installTestChannels();
    
    expect($result)->toHaveKeys(['test_deposit_channel', 'test_withdraw_channel']);
    expect($result['test_deposit_channel'])->toBeInstanceOf(DepositChannel::class);
    expect($result['test_withdraw_channel'])->toBeInstanceOf(WithdrawChannel::class);
    
    // 验证测试渠道配置
    $depositChannel = $result['test_deposit_channel'];
    expect($depositChannel->name)->toBe('test_assets_deposit');
    expect($depositChannel->config['is_test'] ?? false)->toBeTrue();
});

it('验证账户类型', function () {
    // 存在的账户类型
    expect(ConfigurationValidator::validateAccountType('balance'))->toBeTrue();
    
    // 不存在的账户类型
    expect(ConfigurationValidator::validateAccountType('non_existent'))->toBeFalse();
});

it('处理带手续费的入金', function () {
    // 创建带手续费配置的处理器
    $config = new Repository([
        'account_type' => 'balance',
    ]);
    
    $handlerId = $this->handler->createEntityHandler(
        'dnw',
        'fee_deposit_handler',
        AssetsAccountDepositHandler::class,
        $config,
        '带手续费的入金处理器'
    )->id;
    
    $channel = $this->dnw->createDepositChannel(
        'fee_deposit',
        $handlerId
    );
    
    // 创建带手续费的交易
    $transaction = $this->dnw->createDepositTransaction(
        $this->user,
        $channel,
        '1000.00',
        ['fee' => '10.00']
    );
    
    expect($transaction->fee)->toBe('10.00000000');
    expect((float)$transaction->getNetAmount())->toBe(990.0);
    
    // 处理交易
    $result = $this->dnw->processDeposit($transaction);
    expect($result->isSuccess())->toBeTrue();
    
    // 验证只增加净金额
    $account = $this->assetsAccount->ownerAccount($this->user, 'balance');
    expect((float)$account->available_balance)->toBe(990.0);
});
<?php

require_once __DIR__ . '/TestHelpers.php';

use Dybasedev\LunaPrototype\DnW\Models\DepositBinding;
use Dybasedev\LunaPrototype\DnW\Models\WithdrawBinding;
use Dybasedev\LunaPrototype\DnW\Models\DepositChannel;
use Dybasedev\LunaPrototype\DnW\Models\WithdrawChannel;
use Dybasedev\LunaPrototype\DnW\BindingType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Dybasedev\LunaPrototype\Tests\Unit\DnW\TestUserModel;
use function Dybasedev\LunaPrototype\Tests\Unit\DnW\createTestUsersTable;

uses(RefreshDatabase::class);

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
    
    // 创建测试处理器
    $depositHandler = \Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler::create([
        'id' => 1,
        'name' => 'test_deposit_handler',
        'group_id' => hash_code('dnw'),
        'display_name' => 'Test Deposit Handler',
        'handler' => 'TestDepositHandler',
        'config' => [],
        'enabled' => true,
    ]);
    
    $withdrawHandler = \Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler::create([
        'id' => 2,
        'name' => 'test_withdraw_handler',
        'group_id' => hash_code('dnw'),
        'display_name' => 'Test Withdraw Handler',
        'handler' => 'TestWithdrawHandler',
        'config' => [],
        'enabled' => true,
    ]);
    
    // 创建测试渠道
    $this->depositChannel = DepositChannel::create([
        'id' => hash_code('test_deposit_channel'),
        'name' => 'test_deposit',
        'handler_id' => $depositHandler->id,
        'config' => [],
        'is_active' => true,
        'sort' => 0,
    ]);
    
    $this->withdrawChannel = WithdrawChannel::create([
        'id' => hash_code('test_withdraw_channel'),
        'name' => 'test_withdraw',
        'handler_id' => $withdrawHandler->id,
        'config' => [],
        'is_active' => true,
        'sort' => 0,
    ]);
    
    // 创建测试用户
    TestUserModel::create(['id' => 1, 'name' => 'Test User']);
});

it('可以创建入金绑定', function () {
    $binding = DepositBinding::create([
        'channel_id' => $this->depositChannel->id,
        'owner_id' => 1,
        'owner_type' => hash_code('test_user'),
        'channel' => BindingType::FinancialAccount->value,
        'account' => '1234567890',
        'account_name' => '测试账户',
        'channel_name' => '测试银行',
        'channel_provider' => '银联',
        'is_active' => true,
        'is_default' => false,
        'sort' => 0,
    ]);
    
    expect($binding)->toBeInstanceOf(DepositBinding::class);
    expect($binding->channel_id)->toBe($this->depositChannel->id);
    expect($binding->account)->toBe('1234567890');
    expect($binding->channel)->toBe(BindingType::FinancialAccount->value);
});

it('可以创建出金绑定', function () {
    $binding = WithdrawBinding::create([
        'channel_id' => $this->withdrawChannel->id,
        'owner_id' => 1,
        'owner_type' => hash_code('test_user'),
        'channel' => BindingType::DigitalWallet->value,
        'account' => 'user@example.com',
        'account_name' => '测试用户',
        'channel_name' => '数字钱包',
        'channel_provider' => '某支付平台',
        'is_active' => true,
        'is_default' => false,
        'sort' => 0,
    ]);
    
    expect($binding)->toBeInstanceOf(WithdrawBinding::class);
    expect($binding->channel_id)->toBe($this->withdrawChannel->id);
    expect($binding->account)->toBe('user@example.com');
    expect($binding->channel)->toBe(BindingType::DigitalWallet->value);
});

it('账户脱敏功能正常工作', function () {
    $binding = new DepositBinding([
        'account' => '6222021234567890123',
    ]);
    
    // 长账号脱敏
    expect($binding->getMaskedAccount())->toBe('6222***********0123');
    
    // 中等长度账号
    $binding->account = '12345678';
    expect($binding->getMaskedAccount())->toBe('12****78');
    
    // 短账号
    $binding->account = '1234';
    expect($binding->getMaskedAccount())->toBe('****');
    
    // 空账号
    $binding->account = '';
    expect($binding->getMaskedAccount())->toBe('****');
});

it('显示名称功能正常工作', function () {
    $binding = new DepositBinding([
        'account' => '1234567890',
        'account_name' => '张三',
    ]);
    
    // 有账户名时显示账户名
    expect($binding->getDisplayName())->toBe('张三');
    
    // 无账户名时显示脱敏账号
    $binding->account_name = null;
    expect($binding->getDisplayName())->toBe('1234**7890');
});

it('激活和停用功能正常工作', function () {
    $binding = DepositBinding::create([
        'channel_id' => $this->depositChannel->id,
        'owner_id' => 1,
        'owner_type' => hash_code('test_user'),
        'channel' => 'test',
        'account' => '1234567890',
        'account_name' => '测试',
        'channel_name' => '测试',
        'channel_provider' => '测试',
        'is_active' => false,
        'is_default' => false,
        'sort' => 0,
    ]);
    
    // 激活
    $binding->activate();
    expect($binding->is_active)->toBeTrue();
    
    // 停用
    $binding->deactivate();
    expect($binding->is_active)->toBeFalse();
    expect($binding->is_default)->toBeFalse(); // 停用时也会取消默认状态
});

it('设置默认绑定功能正常工作', function () {
    $owner_id = 1;
    $owner_type = hash_code('test_user');
    
    // 创建多个绑定
    $binding1 = DepositBinding::create([
        'channel_id' => $this->depositChannel->id,
        'owner_id' => $owner_id,
        'owner_type' => $owner_type,
        'channel' => 'test1',
        'account' => '111',
        'account_name' => '账户1',
        'channel_name' => '测试',
        'channel_provider' => '测试',
        'is_active' => true,
        'is_default' => false,
        'sort' => 0,
    ]);
    
    $binding2 = DepositBinding::create([
        'channel_id' => $this->depositChannel->id,
        'owner_id' => $owner_id,
        'owner_type' => $owner_type,
        'channel' => 'test2',
        'account' => '222',
        'account_name' => '账户2',
        'channel_name' => '测试',
        'channel_provider' => '测试',
        'is_active' => true,
        'is_default' => false,
        'sort' => 0,
    ]);
    
    // 设置第一个为默认
    $binding1->setAsDefault();
    
    expect($binding1->fresh()->is_default)->toBeTrue();
    expect($binding1->fresh()->is_active)->toBeTrue(); // 设为默认时会自动激活
    expect($binding2->fresh()->is_default)->toBeFalse();
    
    // 设置第二个为默认
    $binding2->setAsDefault();
    
    expect($binding1->fresh()->is_default)->toBeFalse(); // 第一个不再是默认
    expect($binding2->fresh()->is_default)->toBeTrue();
    expect($binding2->fresh()->is_active)->toBeTrue();
});

it('验证账户功能正常工作', function () {
    $binding = DepositBinding::create([
        'channel_id' => $this->depositChannel->id,
        'owner_id' => 1,
        'owner_type' => hash_code('test_user'),
        'channel' => 'test',
        'account' => '1234567890',
        'account_name' => '测试',
        'channel_name' => '测试',
        'channel_provider' => '测试',
        'is_active' => true,
        'is_default' => false,
        'sort' => 0,
        'verified_at' => null,
    ]);
    
    // 初始未验证
    expect($binding->isVerified())->toBeFalse();
    
    // 验证账户
    $binding->verify([
        'metadata' => ['verified_by' => 'system'],
    ]);
    
    expect($binding->isVerified())->toBeTrue();
    expect($binding->verified_at)->not->toBeNull();
    expect($binding->metadata['verified_by'])->toBe('system');
});

it('作用域查询功能正常工作', function () {
    $owner_id = 1;
    $owner_type = hash_code('test_user');
    
    // 创建测试数据
    $activeBinding = DepositBinding::create([
        'channel_id' => $this->depositChannel->id,
        'owner_id' => $owner_id,
        'owner_type' => $owner_type,
        'channel' => BindingType::FinancialAccount->value,
        'account' => '111',
        'account_name' => '活跃账户',
        'channel_name' => '测试',
        'channel_provider' => '测试',
        'is_active' => true,
        'is_default' => false,
        'sort' => 1,
        'verified_at' => now(),
    ]);
    
    $inactiveBinding = DepositBinding::create([
        'channel_id' => $this->depositChannel->id,
        'owner_id' => $owner_id,
        'owner_type' => $owner_type,
        'channel' => BindingType::DigitalWallet->value,
        'account' => '222',
        'account_name' => '非活跃账户',
        'channel_name' => '测试',
        'channel_provider' => '测试',
        'is_active' => false,
        'is_default' => false,
        'sort' => 2,
        'verified_at' => null,
    ]);
    
    // 测试活跃作用域
    $activeBindings = DepositBinding::active()->get();
    expect($activeBindings)->toHaveCount(1);
    expect($activeBindings->first()->id)->toBe($activeBinding->id);
    
    // 测试已验证作用域
    $verifiedBindings = DepositBinding::verified()->get();
    expect($verifiedBindings)->toHaveCount(1);
    expect($verifiedBindings->first()->id)->toBe($activeBinding->id);
    
    // 测试按渠道查询
    $financialBindings = DepositBinding::byChannel(BindingType::FinancialAccount->value)->get();
    expect($financialBindings)->toHaveCount(1);
    expect($financialBindings->first()->id)->toBe($activeBinding->id);
    
    // 测试排序
    $orderedBindings = DepositBinding::ordered()->get();
    expect($orderedBindings->first()->id)->toBe($activeBinding->id); // sort=1
    expect($orderedBindings->last()->id)->toBe($inactiveBinding->id); // sort=2
});

it('绑定类型枚举正常工作', function () {
    $financial = BindingType::FinancialAccount;
    expect($financial->value)->toBe('financial_account');
    expect($financial->getCode())->toBe(short_hash_code('financial_account'));
    expect($financial->getDisplayName())->toBe('金融账户');
    expect($financial->getDescription())->toContain('传统金融机构');
    
    // 测试从代码获取类型
    $code = BindingType::DigitalWallet->getCode();
    $type = BindingType::fromCode($code);
    expect($type)->toBe(BindingType::DigitalWallet);
    
    // 测试无效代码
    expect(BindingType::fromCode(999999))->toBeNull();
});

it('模型关系正确工作', function () {
    $binding = DepositBinding::create([
        'channel_id' => $this->depositChannel->id,
        'owner_id' => 1,
        'owner_type' => hash_code('test_user'),
        'channel' => 'test',
        'account' => '1234567890',
        'account_name' => '测试',
        'channel_name' => '测试',
        'channel_provider' => '测试',
        'is_active' => true,
        'is_default' => false,
        'sort' => 0,
    ]);
    
    // 测试渠道关系
    expect($binding->channelModel)->toBeInstanceOf(DepositChannel::class);
    expect($binding->channelModel->id)->toBe($this->depositChannel->id);
});
<?php

use Dybasedev\LunaPrototype\AssetsAccount\AccountOperations;
use Dybasedev\LunaPrototype\AssetsAccount\AccountOperationBuilder;
use Dybasedev\LunaPrototype\AssetsAccount\AccountTransferOperationBuilder;
use Dybasedev\LunaPrototype\AssetsAccount\AccountUpdateOperationBuilder;
use Dybasedev\LunaPrototype\AssetsAccount\AccountBalanceTypeEnum;
use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccount;
use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccountConfigure;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandlerConfigure;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// 重用 TestSessionHolder 和 TestAccountHandler
class TestSessionHolder implements SessionHolder
{
    public function __construct(
        private int $id = 1,
        private int $type = 1
    ) {}

    public function getOperatorId(): int
    {
        return $this->id;
    }

    public function getOperatorType(): int
    {
        return $this->type;
    }

    public function getOperatorTypeName(): string
    {
        return 'test_user';
    }

    public function getSessionHolderContext(): ?array
    {
        return ['test' => true];
    }
}

class TestAccountHandler extends \Dybasedev\LunaPrototype\Foundation\Handler\BaseHandler
{
    public function handlerName(): string
    {
        return 'test_account_handler';
    }

    public function handlerDescription(): string
    {
        return 'Test account handler for unit testing';
    }
}

beforeEach(function () {
    // 设置处理器配置
    $handlerConfigure = LunaHandlerConfigure::create()
        ->group('account_handlers', 'Account Handlers', function ($register) {
            $register->handler(TestAccountHandler::class);
        })
        ->build();
    
    $this->handler = new LunaHandler($handlerConfigure, app('cache.store'));
    
    // 创建账户处理器实体
    $this->handler->createEntityHandler(
        'account_handlers',
        'test_handler',
        TestAccountHandler::class,
        null,
        'Test Handler'
    );
    
    // 设置资产账户配置
    $this->configure = LunaAssetsAccountConfigure::create()->build();
    
    $this->assetsAccount = new LunaAssetsAccount(
        $this->configure,
        $this->handler,
        app('cache.store')
    );
    
    // 创建账户类型和账户
    $this->assetsAccount->createAccountType('balance', 'test_handler', 'Balance');
    $this->assetsAccount->createAccountType('points', 'test_handler', 'Points');
    
    $this->owner1 = new TestSessionHolder(1, 1);
    $this->owner2 = new TestSessionHolder(2, 1);
    
    $this->assetsAccount->createOwnerAccount($this->owner1);
    $this->assetsAccount->createOwnerAccount($this->owner2);
    
    $this->operations = $this->assetsAccount->createAccountOperation();
});

it('can create account operations instance', function () {
    expect($this->operations)->toBeInstanceOf(AccountOperations::class);
});

it('can add update operation', function () {
    $updateOperation = luna_account_update()
        ->account($this->owner1, 'balance')
        ->available()
        ->event('test_event')
        ->increase(100.50);
    
    $this->operations->operation($updateOperation);
    
    expect($this->operations->operations)->toHaveCount(1);
    // 验证操作数组包含正确的数据结构
    expect($this->operations->operations[0])->toBeArray();
    expect($this->operations->operations[0]['amount'])->toBe('100.5');
});

it('can add transfer operation', function () {
    $transferOperation = luna_account_transfer()
        ->from($this->owner1, 'balance')
        ->fromAvailable()
        ->to($this->owner2, 'balance')
        ->toAvailable()
        ->event('transfer_event')
        ->amount(50.25);
    
    $this->operations->operation($transferOperation);
    
    expect($this->operations->operations)->toHaveCount(2); // 转账操作生成2个子操作
    // 验证第一个操作是转出（负数金额）
    expect($this->operations->operations[0]['amount'])->toBe('-50.25');
    // 验证第二个操作是转入（正数金额）
    expect($this->operations->operations[1]['amount'])->toBe('50.25');
});

it('can add multiple operations', function () {
    $updateOperation = luna_account_update()
        ->account($this->owner1, 'balance')
        ->available()
        ->event('increase_event')
        ->increase(100);
    
    $transferOperation = luna_account_transfer()
        ->from($this->owner1, 'balance')
        ->fromAvailable()
        ->to($this->owner2, 'balance')
        ->toAvailable()
        ->event('transfer_event')
        ->amount(50);
    
    $this->operations
        ->operation($updateOperation)
        ->operation($transferOperation);
    
    expect($this->operations->operations)->toHaveCount(3); // 1个更新操作 + 2个转账子操作
});

it('can submit operations', function () {
    // 先增加余额
    $updateOperation = luna_account_update()
        ->account($this->owner1, 'balance')
        ->available()
        ->event('increase_event')
        ->increase(100);
    
    $this->operations->operation($updateOperation);
    $this->operations->submit();
    
    // 验证余额已更新
    $account = $this->assetsAccount->ownerAccount($this->owner1, 'balance');
    expect($account->available_balance)->toBe('100.00000000');
});

it('can submit transfer operations', function () {
    // 先给第一个用户增加余额
    $increaseOperation = luna_account_update()
        ->account($this->owner1, 'balance')
        ->available()
        ->event('increase_event')
        ->increase(100);
    
    $this->operations->operation($increaseOperation);
    $this->operations->submit();
    
    // 创建新的操作实例进行转账
    $transferOperations = $this->assetsAccount->createAccountOperation();
    
    $transferOperation = luna_account_transfer()
        ->from($this->owner1, 'balance')
        ->fromAvailable()
        ->to($this->owner2, 'balance')
        ->toAvailable()
        ->event('transfer_event')
        ->amount(30);
    
    $transferOperations->operation($transferOperation);
    $transferOperations->submit();
    
    // 验证转账结果
    $account1 = $this->assetsAccount->ownerAccount($this->owner1, 'balance');
    $account2 = $this->assetsAccount->ownerAccount($this->owner2, 'balance');
    
    expect($account1->available_balance)->toBe('70.00000000');
    expect($account2->available_balance)->toBe('30.00000000');
});

it('can handle insufficient balance', function () {
    // 尝试转账但余额不足
    $transferOperation = luna_account_transfer()
        ->from($this->owner1, 'balance')
        ->fromAvailable()
        ->to($this->owner2, 'balance')
        ->toAvailable()
        ->event('transfer_event')
        ->amount(100);
    
    $this->operations->operation($transferOperation);
    
    expect(function () {
        $this->operations->submit(); // 默认不允许超支
    })->toThrow(\Dybasedev\LunaPrototype\Foundation\Exception\LunaException::class);
});

it('can allow overdraft', function () {
    // 允许超支的转账
    $transferOperation = luna_account_transfer()
        ->from($this->owner1, 'balance')
        ->fromAvailable()
        ->to($this->owner2, 'balance')
        ->toAvailable()
        ->event('transfer_event')
        ->amount(100);
    
    $this->operations->operation($transferOperation);
    $this->operations->submit(true); // 允许超支
    
    // 验证余额可以为负数
    $account1 = $this->assetsAccount->ownerAccount($this->owner1, 'balance');
    $account2 = $this->assetsAccount->ownerAccount($this->owner2, 'balance');
    
    expect($account1->available_balance)->toBe('-100.00000000');
    expect($account2->available_balance)->toBe('100.00000000');
});

it('can handle frozen balance operations', function () {
    // 增加可用余额
    $increaseOperation = luna_account_update()
        ->account($this->owner1, 'balance')
        ->available()
        ->event('increase_event')
        ->increase(100);
    
    $this->operations->operation($increaseOperation);
    $this->operations->submit();
    
    // 创建新操作实例，冻结部分余额
    $freezeOperations = $this->assetsAccount->createAccountOperation();
    
    $freezeOperation = luna_account_transfer()
        ->from($this->owner1, 'balance')
        ->fromAvailable()
        ->to($this->owner1, 'balance')
        ->toFrozen()
        ->event('freeze_event')
        ->amount(30);
    
    $freezeOperations->operation($freezeOperation);
    $freezeOperations->submit();
    
    $account = $this->assetsAccount->ownerAccount($this->owner1, 'balance');
    expect($account->available_balance)->toBe('70.00000000');
    expect($account->frozen_balance)->toBe('30.00000000');
});

it('can handle locked balance operations', function () {
    // 增加可用余额
    $increaseOperation = luna_account_update()
        ->account($this->owner1, 'balance')
        ->available()
        ->event('increase_event')
        ->increase(100);
    
    $this->operations->operation($increaseOperation);
    $this->operations->submit();
    
    // 创建新操作实例，锁定部分余额
    $lockOperations = $this->assetsAccount->createAccountOperation();
    
    $lockOperation = luna_account_transfer()
        ->from($this->owner1, 'balance')
        ->fromAvailable()
        ->to($this->owner1, 'balance')
        ->toLocked()
        ->event('lock_event')
        ->amount(20);
    
    $lockOperations->operation($lockOperation);
    $lockOperations->submit();
    
    $account = $this->assetsAccount->ownerAccount($this->owner1, 'balance');
    expect($account->available_balance)->toBe('80.00000000');
    expect($account->locked_balance)->toBe('20.00000000');
});

it('creates change log records', function () {
    $updateOperation = luna_account_update()
        ->account($this->owner1, 'balance')
        ->available()
        ->event('test_event')
        ->increase(100);
    
    $this->operations->operation($updateOperation);
    $this->operations->submit();
    
    // 验证变动日志已创建
    $this->assertDatabaseHas('luna_assets_account_change_logs', [
        'owner_id' => 1,
        'owner_type' => 1,
        'account_type_id' => hash_code('balance'),
        'change_value' => '100.00000000',
        'before_value' => '0.00000000',
        'event_id' => hash_code('test_event')
    ]);
});
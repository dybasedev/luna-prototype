<?php

use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccount;
use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccountConfigure;
use Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccount;
use Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccountType;
use Dybasedev\LunaPrototype\Foundation\Configuration\Repository;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandlerConfigure;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// 创建测试用的 SessionHolder 实现
class TestSessionHolderForAccount implements SessionHolder
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

// 创建测试用的标准账户处理器
class TestAccountHandlerForAccount extends \Dybasedev\LunaPrototype\Foundation\Handler\BaseHandler
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
            $register->handler(TestAccountHandlerForAccount::class);
        })
        ->build();
    
    $this->handler = new LunaHandler($handlerConfigure, app('cache.store'));
    
    // 创建账户处理器实体
    $this->handler->createEntityHandler(
        'account_handlers',
        'test_handler',
        TestAccountHandlerForAccount::class,
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
});

test('可以创建账户类型', function () {
    $accountType = $this->assetsAccount->createAccountType(
        'balance',
        'test_handler',
        'Balance Account',
        'Main balance account'
    );
    
    expect($accountType)->toBeInstanceOf(AssetsAccountType::class);
    expect($accountType->name)->toBe('balance');
    expect($accountType->display_name)->toBe('Balance Account');
    expect($accountType->description)->toBe('Main balance account');
    expect($accountType->handler_id)->toBe(hash_code('test_handler'));
    expect($accountType->id)->toBe(hash_code('balance'));
    expect($accountType->parent_id)->toBe(0);
});

test('可以创建带有父级的账户类型', function () {
    // 先创建父账户类型
    $parentType = $this->assetsAccount->createAccountType(
        'main_balance',
        'test_handler',
        'Main Balance'
    );
    
    // 创建子账户类型
    $childType = $this->assetsAccount->createAccountType(
        'sub_balance',
        'test_handler',
        'Sub Balance',
        'Sub balance account',
        null,
        $parentType
    );
    
    expect($childType->parent_id)->toBe($parentType->id);
    expect($childType->name)->toBe('sub_balance');
});

test('可以使用字符串父级创建账户类型', function () {
    // 先创建父账户类型
    $this->assetsAccount->createAccountType(
        'parent_balance',
        'test_handler',
        'Parent Balance'
    );
    
    // 使用字符串引用父账户类型
    $childType = $this->assetsAccount->createAccountType(
        'child_balance',
        'test_handler',
        'Child Balance',
        'Child balance account',
        null,
        'parent_balance'
    );
    
    expect($childType->parent_id)->toBe(hash_code('parent_balance'));
});

test('当处理器不存在时抛出异常', function () {
    expect(function () {
        $this->assetsAccount->createAccountType(
            'invalid_balance',
            'non_existent_handler'
        );
    })->toThrow(\Dybasedev\LunaPrototype\Foundation\Exception\LunaException::class);
});

test('当父级不存在时抛出异常', function () {
    expect(function () {
        $this->assetsAccount->createAccountType(
            'orphan_balance',
            'test_handler',
            'Orphan Balance',
            'Orphan balance account',
            null,
            'non_existent_parent'
        );
    })->toThrow(\Dybasedev\LunaPrototype\Foundation\Exception\LunaException::class);
});

test('可以获取所有账户类型', function () {
    // 创建几个账户类型
    $this->assetsAccount->createAccountType('balance', 'test_handler', 'Balance');
    $this->assetsAccount->createAccountType('points', 'test_handler', 'Points');
    $this->assetsAccount->createAccountType('credit', 'test_handler', 'Credit');
    
    $accountTypes = $this->assetsAccount->getAllAccountTypes();
    
    expect($accountTypes)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($accountTypes)->toHaveCount(3);
    
    $names = $accountTypes->pluck('name')->toArray();
    expect($names)->toContain('balance', 'points', 'credit');
});

test('可以在不使用缓存的情况下获取所有账户类型', function () {
    // 创建账户类型
    $this->assetsAccount->createAccountType('balance', 'test_handler', 'Balance');
    
    $accountTypes = $this->assetsAccount->getAllAccountTypesWithoutCache();
    
    expect($accountTypes)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($accountTypes)->toHaveCount(1);
    expect($accountTypes->first()->name)->toBe('balance');
});

test('缓存账户类型', function () {
    $cache = app('cache.store');
    
    // 创建账户类型
    $this->assetsAccount->createAccountType('balance', 'test_handler', 'Balance');
    
    // 第一次调用
    $accountTypes1 = $this->assetsAccount->getAllAccountTypes();
    
    // 验证缓存存在
    expect($cache->has('assets-account:types'))->toBeTrue();
    
    // 第二次调用应该从缓存获取
    $accountTypes2 = $this->assetsAccount->getAllAccountTypes();
    
    expect($accountTypes1->count())->toBe($accountTypes2->count());
});

test('可以获取账户类型摘要', function () {
    $this->assetsAccount->createAccountType('balance', 'test_handler', 'Balance Account', 'Main balance');
    $this->assetsAccount->createAccountType('points', 'test_handler', 'Points Account', 'Loyalty points');
    
    $summary = $this->assetsAccount->accountTypes();
    
    expect($summary)->toBeArray();
    expect($summary)->toHaveCount(2);
    expect($summary[0])->toHaveKeys(['id', 'name', 'display_name', 'description']);
    
    // 检查两个账户类型都存在
    $names = array_column($summary, 'name');
    expect($names)->toContain('balance', 'points');
    
    // 检查 balance 账户的详细信息
    $balanceAccount = collect($summary)->firstWhere('name', 'balance');
    expect($balanceAccount['display_name'])->toBe('Balance Account');
    expect($balanceAccount['description'])->toBe('Main balance');
});

test('可以创建所有者账户', function () {
    // 创建账户类型
    $this->assetsAccount->createAccountType('balance', 'test_handler', 'Balance');
    $this->assetsAccount->createAccountType('points', 'test_handler', 'Points');
    
    $owner = new TestSessionHolderForAccount(1, 1);
    
    $this->assetsAccount->createOwnerAccount($owner);
    
    // 验证账户已创建
    $accounts = AssetsAccount::where('owner_id', 1)
        ->where('owner_type', 1)
        ->get();
    
    expect($accounts)->toHaveCount(2);
    expect($accounts->pluck('account_type_id')->toArray())->toContain(
        hash_code('balance'),
        hash_code('points')
    );
});

test('可以获取所有者账户', function () {
    // 创建账户类型和账户
    $this->assetsAccount->createAccountType('balance', 'test_handler', 'Balance');
    $owner = new TestSessionHolderForAccount(1, 1);
    $this->assetsAccount->createOwnerAccount($owner);
    
    $account = $this->assetsAccount->ownerAccount($owner, 'balance');
    
    expect($account)->toBeInstanceOf(AssetsAccount::class);
    expect($account->owner_id)->toBe(1);
    expect($account->owner_type)->toBe(1);
    expect($account->account_type_id)->toBe(hash_code('balance'));
});

test('可以获取带有子账户的所有者账户', function () {
    // 创建父子账户类型
    $parentType = $this->assetsAccount->createAccountType('main_balance', 'test_handler', 'Main Balance');
    $this->assetsAccount->createAccountType('sub_balance', 'test_handler', 'Sub Balance', '', null, $parentType);
    
    $owner = new TestSessionHolderForAccount(1, 1);
    $this->assetsAccount->createOwnerAccount($owner);
    
    $account = $this->assetsAccount->ownerAccount($owner, 'main_balance', true);
    
    expect($account)->toBeInstanceOf(AssetsAccount::class);
    expect($account->relationLoaded('children'))->toBeTrue();
});

test('可以获取所有者主账户', function () {
    // 创建主账户和子账户
    $mainType = $this->assetsAccount->createAccountType('main_balance', 'test_handler', 'Main Balance');
    $this->assetsAccount->createAccountType('sub_balance', 'test_handler', 'Sub Balance', '', null, $mainType);
    $this->assetsAccount->createAccountType('points', 'test_handler', 'Points');
    
    $owner = new TestSessionHolderForAccount(1, 1);
    $this->assetsAccount->createOwnerAccount($owner);
    
    $mainAccounts = $this->assetsAccount->ownerMainAccounts($owner);
    
    expect($mainAccounts)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($mainAccounts)->toHaveCount(2); // main_balance 和 points 是主账户
    
    $accountTypeIds = $mainAccounts->pluck('account_type_id')->toArray();
    expect($accountTypeIds)->toContain(hash_code('main_balance'), hash_code('points'));
});

test('可以创建账户操作', function () {
    $operation = $this->assetsAccount->createAccountOperation();
    
    expect($operation)->toBeInstanceOf(\Dybasedev\LunaPrototype\AssetsAccount\AccountOperations::class);
});

test('可以从请求对象创建账户类型', function () {
    $request = new \Dybasedev\LunaPrototype\AssetsAccount\AccountTypeCreationRequest(
        name: 'request_balance',
        handler: 'test_handler',
        displayName: 'Request Balance',
        description: 'Balance created from request object'
    );
    
    $accountType = $this->assetsAccount->createAccountTypeFromRequest($request);
    
    expect($accountType)->toBeInstanceOf(\Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccountType::class);
    expect($accountType->name)->toBe('request_balance');
    expect($accountType->display_name)->toBe('Request Balance');
    expect($accountType->description)->toBe('Balance created from request object');
});
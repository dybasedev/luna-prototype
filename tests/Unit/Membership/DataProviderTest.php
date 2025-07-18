<?php

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Membership\Milestone\DataProviders\CallbackDataProvider;
use Dybasedev\LunaPrototype\Membership\Milestone\DataProviders\CommonDataProviders;
use Dybasedev\LunaPrototype\Membership\Milestone\DataProviders\QueryDataProvider;
use Dybasedev\LunaPrototype\Membership\Milestone\DataProviderRegistry;
use Dybasedev\LunaPrototype\Membership\Milestone\Conditions\DataProviderCondition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// 测试用的 SessionHolder
class MembershipTestSessionHolder implements SessionHolder
{
    public function __construct(
        public int $id = 1,
        public string $type = 'user'
    ) {
    }
    
    public function getOperatorTypeName(): string
    {
        return $this->type;
    }
    
    public function getOperatorType(): int
    {
        return hash_code($this->type);
    }
    
    public function getOperatorId(): int
    {
        return $this->id;
    }
    
    public function getSessionHolderContext(): ?array
    {
        return null;
    }
}

beforeEach(function () {
    // 创建测试表
    Schema::create('orders', function ($table) {
        $table->id();
        $table->integer('user_id');
        $table->integer('user_type');
        $table->decimal('total_amount', 10, 2);
        $table->string('status', 20);
        $table->timestamps();
    });
    
    // 插入测试数据
    DB::table('orders')->insert([
        ['user_id' => 1, 'user_type' => hash_code('user'), 'total_amount' => 100, 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => 1, 'user_type' => hash_code('user'), 'total_amount' => 200, 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => 1, 'user_type' => hash_code('user'), 'total_amount' => 300, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => 2, 'user_type' => hash_code('user'), 'total_amount' => 500, 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('回调数据提供者', function () {
    $provider = new CallbackDataProvider(
        'test_provider',
        function (SessionHolder $owner, array $params) {
            return $owner->getOperatorId() * 100 + ($params['bonus'] ?? 0);
        }
    );
    
    $owner = new MembershipTestSessionHolder(5);
    
    expect($provider->getName())->toBe('test_provider');
    expect($provider->getData($owner))->toBe(500);
    expect($provider->getData($owner, ['bonus' => 50]))->toBe(550);
});

test('查询数据提供者 - 总和', function () {
    $provider = new QueryDataProvider(
        'total_amount',
        'orders',
        'sum',
        'total_amount',
        ['status' => 'completed'],
        'user_id',
        'user_type'
    );
    
    $owner = new MembershipTestSessionHolder(1);
    
    expect($provider->getData($owner))->toBe(300.0);
});

test('查询数据提供者 - 计数', function () {
    $provider = new QueryDataProvider(
        'order_count',
        'orders',
        'count',
        'id',
        ['status' => 'completed'],
        'user_id',
        'user_type'
    );
    
    $owner = new MembershipTestSessionHolder(1);
    
    expect($provider->getData($owner))->toBe(2);
});

test('查询数据提供者 - 批量获取', function () {
    $provider = new QueryDataProvider(
        'total_amount',
        'orders',
        'sum',
        'total_amount',
        ['status' => 'completed'],
        'user_id',
        'user_type'
    );
    
    $owners = [
        new MembershipTestSessionHolder(1),
        new MembershipTestSessionHolder(2),
        new MembershipTestSessionHolder(3), // 没有订单的用户
    ];
    
    $results = $provider->getBatchData($owners);
    
    expect($results)->toBe([
        1 => 300.0,
        2 => 500.0,
        3 => 0,
    ]);
});

test('数据提供者注册表', function () {
    $registry = new DataProviderRegistry();
    
    $provider1 = new CallbackDataProvider('provider1', fn() => 100);
    $provider2 = new CallbackDataProvider('provider2', fn() => 200);
    
    $registry->register($provider1);
    $registry->register($provider2);
    
    expect($registry->has('provider1'))->toBeTrue();
    expect($registry->has('provider2'))->toBeTrue();
    expect($registry->has('provider3'))->toBeFalse();
    
    expect($registry->get('provider1'))->toBe($provider1);
    expect($registry->get('provider2'))->toBe($provider2);
    expect($registry->get('provider3'))->toBeNull();
    
    $registry->remove('provider1');
    expect($registry->has('provider1'))->toBeFalse();
    
    expect($registry->all())->toHaveCount(1);
    
    $registry->clear();
    expect($registry->all())->toHaveCount(0);
});

test('数据提供者条件', function () {
    $provider = new CallbackDataProvider(
        'score_provider',
        function (SessionHolder $owner, array $params) {
            return $owner->getOperatorId() * 100;
        }
    );
    
    $condition = new DataProviderCondition(
        $provider,
        '>=',
        200,
        'high_score',
        'Score >= 200'
    );
    
    $owner1 = new MembershipTestSessionHolder(1);
    $owner2 = new MembershipTestSessionHolder(2);
    $owner3 = new MembershipTestSessionHolder(3);
    
    expect($condition->isSatisfied($owner1))->toBeFalse();
    expect($condition->isSatisfied($owner2))->toBeTrue();
    expect($condition->isSatisfied($owner3))->toBeTrue();
    
    expect($condition->getIdentifier())->toBe('high_score');
    expect($condition->getDescription())->toBe('Score >= 200');
});

test('常用数据提供者 - 用户消费总额', function () {
    $provider = CommonDataProviders::userTotalConsumption('orders', 'total_amount');
    
    $owner = new MembershipTestSessionHolder(1);
    
    expect($provider->getName())->toBe('user_total_consumption');
    expect($provider->getData($owner))->toBe(300.0);
});

test('常用数据提供者 - 缓存', function () {
    $callCount = 0;
    $provider = CommonDataProviders::cached(
        'cached_test',
        function ($owner, $params) use (&$callCount) {
            $callCount++;
            return $owner->getOperatorId() * 100;
        },
        60
    );
    
    $owner = new MembershipTestSessionHolder(1);
    
    // 第一次调用
    expect($provider->getData($owner))->toBe(100);
    expect($callCount)->toBe(1);
    
    // 第二次调用应该从缓存获取
    expect($provider->getData($owner))->toBe(100);
    expect($callCount)->toBe(1);
    
    // 清除缓存
    Cache::forget("milestone_data:cached_test:" . hash_code('user') . ":1");
    
    // 第三次调用应该重新计算
    expect($provider->getData($owner))->toBe(100);
    expect($callCount)->toBe(2);
});

test('常用数据提供者 - 组合', function () {
    $provider1 = new CallbackDataProvider('p1', fn($owner) => 100);
    $provider2 = new CallbackDataProvider('p2', fn($owner) => 200);
    $provider3 = new CallbackDataProvider('p3', fn($owner) => 300);
    
    $combinedProvider = CommonDataProviders::combined(
        'combined',
        [
            'a' => $provider1,
            'b' => $provider2,
            'c' => $provider3,
        ],
        function (array $results) {
            return $results['a'] + $results['b'] + $results['c'];
        }
    );
    
    $owner = new MembershipTestSessionHolder(1);
    
    expect($combinedProvider->getData($owner))->toBe(600);
});

test('数据提供者条件 - 复杂操作符', function () {
    $provider = new CallbackDataProvider('test', fn() => 5);
    
    // 测试 'in' 操作符
    $inCondition = new DataProviderCondition($provider, 'in', [1, 3, 5, 7]);
    expect($inCondition->isSatisfied(new MembershipTestSessionHolder()))->toBeTrue();
    
    // 测试 'not_in' 操作符
    $notInCondition = new DataProviderCondition($provider, 'not_in', [2, 4, 6, 8]);
    expect($notInCondition->isSatisfied(new MembershipTestSessionHolder()))->toBeTrue();
    
    // 测试 'between' 操作符
    $betweenCondition = new DataProviderCondition($provider, 'between', [3, 7]);
    expect($betweenCondition->isSatisfied(new MembershipTestSessionHolder()))->toBeTrue();
    
    $notBetweenCondition = new DataProviderCondition($provider, 'between', [6, 10]);
    expect($notBetweenCondition->isSatisfied(new MembershipTestSessionHolder()))->toBeFalse();
});
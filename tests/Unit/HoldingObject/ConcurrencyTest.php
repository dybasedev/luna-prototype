<?php

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\HoldingObject\Examples\LotteryChanceObject;
use Dybasedev\LunaPrototype\HoldingObject\LunaHoldingObject;
use Dybasedev\LunaPrototype\HoldingObject\LunaHoldingObjectConfigure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

class TestSessionHolderForConcurrency implements SessionHolder
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
        return null;
    }
}

beforeEach(function () {
    // 配置组件
    $this->configure = LunaHoldingObjectConfigure::create()
        ->registerUniqueObject('lottery-chance', LotteryChanceObject::class)
        ->build();
    
    // 使用数据库 upsert 处理并发
    $this->configure->setUseDbUniqueConflictHandling(true)
        ->setUseCacheLock(false);

    $this->holdingObject = new LunaHoldingObject($this->configure, app('cache.store'));
    
    // 创建测试用户
    $this->owner = new TestSessionHolderForConcurrency(1, 1);
});

it('handles concurrent creation with database upsert', function () {
    $lotteryId = 2024;
    
    // 模拟并发创建（在实际环境中会是真正的并发）
    $holdings = [];
    for ($i = 0; $i < 5; $i++) {
        $holding = $this->holdingObject->createUniqueHolding(
            $this->owner,
            'lottery-chance',
            $lotteryId,
            ['source' => 'system_grant', 'index' => $i],
            1.0
        );
        $holdings[] = $holding;
    }
    
    // 验证最终结果 - 强制不使用缓存
    $finalHolding = $this->holdingObject->getUniqueHolding($this->owner, 'lottery-chance', $lotteryId, true);
    expect($finalHolding)->not->toBeNull();
    expect($finalHolding->quantity)->toBe('5.00000000');
    
    // 所有返回的持有记录应该是同一个
    foreach ($holdings as $holding) {
        expect($holding->id)->toBe($finalHolding->id);
    }
});

it('prevents multiple holdings when not allowed', function () {
    // 注册一个不允许多次持有的对象
    $this->configure->registerUniqueObject('single-use-coupon', new class extends \Dybasedev\LunaPrototype\HoldingObject\UniqueObject {
        protected(set) ?string $name = 'single-use-coupon';
        protected(set) bool $enableHoldMultiple = false;
        public string $conflictMessage = '该优惠券只能领取一次';
        
        public function reformatId(string|int $id): string|int
        {
            return $id;
        }
        
        public function permit(SessionHolder $owner, string|int $objectId, array $payload = []): bool
        {
            return true;
        }
        
        public function validatePayload(array $payload): bool
        {
            return true;
        }
    });
    
    $this->holdingObject = new LunaHoldingObject($this->configure, app('cache.store'));
    
    // 第一次创建成功
    $holding = $this->holdingObject->createUniqueHolding(
        $this->owner,
        'single-use-coupon',
        'COUPON001',
        ['claimed_at' => now()->toDateTimeString()],
        1.0
    );
    
    expect($holding)->not->toBeNull();
    
    // 第二次创建失败
    expect(fn () => $this->holdingObject->createUniqueHolding(
        $this->owner,
        'single-use-coupon',
        'COUPON001',
        ['claimed_at' => now()->toDateTimeString()],
        1.0
    ))->toThrow(\Dybasedev\LunaPrototype\Foundation\Exception\LunaException::class);
});

it('handles cache lock when available', function () {
    // 如果当前缓存驱动支持锁
    if (method_exists(Cache::store(), 'lock')) {
        $configure = LunaHoldingObjectConfigure::create()
            ->registerUniqueObject('lottery-chance', LotteryChanceObject::class)
            ->build();
        
        $configure->setUseCacheLock(true)
            ->setLockTimeout(2)
            ->setLockWaitTimeout(1);
        
        $holdingObject = new LunaHoldingObject($configure, Cache::store());
        
        // 创建持有记录
        $holding = $holdingObject->createUniqueHolding(
            $this->owner,
            'lottery-chance',
            2025,
            ['source' => 'test_lock'],
            1.0
        );
        
        expect($holding)->not->toBeNull();
        expect($holding->quantity)->toBe('1.00000000');
    } else {
        $this->markTestSkipped('Current cache driver does not support locks');
    }
});

it('gracefully handles cache driver without lock support', function () {
    // 使用 array 缓存驱动（可能不支持锁）
    $configure = LunaHoldingObjectConfigure::create()
        ->registerUniqueObject('lottery-chance', LotteryChanceObject::class)
        ->build();
    
    $configure->setUseCacheLock(true);
    
    $holdingObject = new LunaHoldingObject($configure, Cache::driver('array'));
    
    // 即使缓存驱动不支持锁，也应该能正常工作
    $holding = $holdingObject->createUniqueHolding(
        $this->owner,
        'lottery-chance',
        2026,
        ['source' => 'system_grant'],
        1.0
    );
    
    expect($holding)->not->toBeNull();
    expect($holding->quantity)->toBe('1.00000000');
});
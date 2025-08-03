<?php

use Dybasedev\LunaPrototype\Trade\LunaTrade;
use Dybasedev\LunaPrototype\Trade\LunaTradeConfigure;
use Dybasedev\LunaPrototype\Trade\Standard\StandardTradeFlowHandler;
use Dybasedev\LunaPrototype\Trade\Standard\StandardTransactionStatus;
use Dybasedev\LunaPrototype\Trade\Tradable;
use Dybasedev\LunaPrototype\Trade\Models\TradeTradable;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// 测试用户类
class TestTradeUser implements SessionHolder
{
    public function __construct(public int $id = 1) {}
    
    public function getOperatorTypeName(): string
    {
        return 'test_user';
    }
    
    public function getOperatorType(): int
    {
        return hash_code('test_user');
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

// 测试用可交易对象
class TestProduct implements Tradable
{
    public function __construct(
        public int $id,
        public string $name,
        public float $price,
        public float $stock = 100.0
    ) {}
    
    public function getTradableId(): int|string
    {
        return $this->id;
    }
    
    public function getTradableType(): string
    {
        return 'test_product';
    }
    
    public function getTradableName(): string
    {
        return $this->name;
    }
    
    public function getTradableDescription(): string
    {
        return 'Test product: ' . $this->name;
    }
    
    public function getTradablePrice(): float
    {
        return $this->price;
    }
    
    public function getTradableOriginPrice(): float
    {
        return $this->price; // Keep same as actual price for testing
    }
    
    public function getTradablePriceUnit(): string|int|null
    {
        return null;
    }
    
    public function isTradableAvailable(): bool
    {
        return true;
    }
    
    public function checkTradableStock(float $quantity): bool
    {
        return $this->stock >= $quantity;
    }
    
    public function getTradablePayload(): array
    {
        return ['test' => true];
    }
    
    public function getTradableProvider(): ?array
    {
        return null;
    }
}

beforeEach(function () {
    // 加载迁移
    $this->loadMigrationsFrom(__DIR__ . '/../../../src/Foundation/Handler/migrations');
    $this->loadMigrationsFrom(__DIR__ . '/../../../src/Trade/migrations');
    
    // 设置Handler
    $handlerConfigure = \Dybasedev\LunaPrototype\Foundation\Handler\LunaHandlerConfigure::create()
        ->group('trade', '交易', function ($register) {
            $register->handler(StandardTradeFlowHandler::class);
        })
        ->build();
        
    $this->handler = new LunaHandler(
        $handlerConfigure,
        app('cache.store')
    );
    
    // 创建标准交易流程处理器
    $this->handler->createEntityHandler(
        'trade',
        'standard',
        StandardTradeFlowHandler::class,
        null,
        'Standard trade flow'
    );
    
    app()->instance(LunaHandler::class, $this->handler);
    
    // 设置Trade组件
    $tradeConfigure = LunaTradeConfigure::create()
        ->build();
        
    // 初始化编号生成器（在测试环境中需要手动调用boot）
    $tradeConfigure->boot(app());
        
    $this->trade = new LunaTrade(
        $tradeConfigure,
        $this->handler,
        app('cache.store')
    );
    
    app()->instance(LunaTrade::class, $this->trade);
    app()->instance('luna.trade', $this->trade);
    app()->instance(LunaTradeConfigure::class, $tradeConfigure);
});

it('可以创建单个商品的交易', function () {
    $user = new TestTradeUser(1);
    $product = new TestProduct(1, 'iPhone 15', 5999.00);
    
    $transaction = $this->trade->createTransaction(
        $user,
        'standard',
        $product,
        ['quantity' => 2]
    );
    
    expect($transaction)->not->toBeNull();
    expect($transaction->owner_id)->toBe(1);
    expect($transaction->owner_type)->toBe(hash_code('test_user'));
    expect($transaction->status)->toBe(StandardTransactionStatus::PendingPayment->getCode());
    
    // The actual amount is based on the tradable price (5999 * 2 = 11998)
    expect((float)$transaction->amount)->toBe(11998.00);
    expect((float)$transaction->origin_amount)->toBe(11998.00); // Same as actual since we made origin price = price
    expect($transaction->multi_tradables)->toBeFalse();
    expect($transaction->getTransactionNumber())->toStartWith('T');
});

it('可以创建多个商品的交易', function () {
    $user = new TestTradeUser(1);
    $product1 = new TestProduct(1, 'iPhone 15', 5999.00);
    $product2 = new TestProduct(2, 'AirPods Pro', 1999.00);
    
    $transaction = $this->trade->createTransaction(
        $user,
        'standard',
        [$product1, $product2],
        [
            'quantities' => [
                1 => 1,
                2 => 2,
            ]
        ]
    );
    
    expect($transaction)->not->toBeNull();
    expect($transaction->multi_tradables)->toBeTrue();
    expect((float)$transaction->amount)->toBe(9997.00); // 5999 + 1999*2
    
    // 检查关联的商品
    $tradables = $transaction->tradables;
    expect($tradables)->toHaveCount(2);
});

it('可以更新交易状态', function () {
    $user = new TestTradeUser(1);
    $product = new TestProduct(1, 'Test Product', 100.00);
    
    $transaction = $this->trade->createTransaction($user, 'standard', $product);
    
    // 更新为已支付状态
    $result = $this->trade->updateTransactionStatus(
        $transaction,
        StandardTransactionStatus::Paid->getCode(),
        ['payment_method' => 'test']
    );
    
    expect($result)->toBeInstanceOf(\Dybasedev\LunaPrototype\Trade\StatusChangeResult::class);
    expect($result->isSuccess())->toBeTrue();
    expect($transaction->status)->toBe(StandardTransactionStatus::Paid->getCode());
});

it('可以取消交易', function () {
    $user = new TestTradeUser(1);
    $product = new TestProduct(1, 'Test Product', 100.00);
    
    $transaction = $this->trade->createTransaction($user, 'standard', $product);
    
    $this->trade->cancelTransaction($transaction, '用户取消');
    
    expect($transaction->status)->toBe(StandardTransactionStatus::Canceled->getCode());
    expect($transaction->is_finished)->toBeTrue();
    expect($transaction->canceled_at)->not->toBeNull();
});

it('可以查询用户的交易列表', function () {
    $user = new TestTradeUser(1);
    $product = new TestProduct(1, 'Test Product', 100.00);
    
    // 创建多个交易
    for ($i = 0; $i < 5; $i++) {
        $this->trade->createTransaction($user, 'standard', $product);
    }
    
    $transactions = $this->trade->getOwnerTransactions($user, [], 10);
    
    expect($transactions->total())->toBe(5);
    expect($transactions->items())->toHaveCount(5);
});

it('可以使用辅助函数', function () {
    expect(luna_trade())->toBe($this->trade);
    
    $user = new TestTradeUser(1);
    $product = new TestProduct(1, 'Test Product', 100.00);
    
    $transaction = luna_create_trade($user, 'standard', $product, 2.0);
    
    expect($transaction)->not->toBeNull();
    expect((float)$transaction->amount)->toBe(200.00); // 100 * 2
});

test('可以创建标准可交易对象', function () {
    $tradable = $this->trade->createTradable([
        'code' => 'PROD001',
        'name' => 'Test Product',
        'title' => 'Test Product Title',
        'amount' => 99.99,
        'origin_amount' => 129.99,
        'stock' => 50,
        'handler_id' => hash_code('standard'),
        'status' => 1,
    ]);
    
    expect($tradable)->toBeInstanceOf(TradeTradable::class);
    expect($tradable->code)->toBe('PROD001');
    expect((float)$tradable->amount)->toBe(99.99);
    expect($tradable->isTradableAvailable())->toBeTrue();
});

test('库存不足时无法创建交易', function () {
    $user = new TestTradeUser(1);
    $product = new TestProduct(1, 'Test Product', 100.00, 5.0); // 只有5个库存
    
    expect(fn() => $this->trade->createTransaction(
        $user,
        'standard',
        $product,
        ['quantity' => 10] // 要买10个
    ))->toThrow(\Dybasedev\LunaPrototype\Foundation\Exception\LunaException::class);
});
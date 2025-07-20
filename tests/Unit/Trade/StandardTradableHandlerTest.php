<?php

use Dybasedev\LunaPrototype\Trade\Standard\StandardTradableHandler;
use Dybasedev\LunaPrototype\Trade\Tradable;
use Dybasedev\LunaPrototype\Trade\TransactionContext;
use Dybasedev\LunaPrototype\Trade\Models\TradeTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// 测试用可交易对象
class TestHandlerProduct implements Tradable
{
    public function __construct(
        public int $id,
        public string $name,
        public float $price,
        public float $originPrice,
        public float $stock = 100.0,
        public bool $available = true
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
        return $this->originPrice;
    }
    
    public function getTradablePriceUnit(): string|int|null
    {
        return null;
    }
    
    public function isTradableAvailable(): bool
    {
        return $this->available;
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
        return ['name' => 'Test Provider', 'id' => 1];
    }
}

beforeEach(function () {
    // 加载迁移
    $this->loadMigrationsFrom(__DIR__ . '/../../../src/Trade/migrations');
    
    $this->handler = new StandardTradableHandler();
});

test('可以计算商品价格', function () {
    $product = new TestHandlerProduct(1, 'iPhone 15', 999.00, 1299.00);
    
    $priceInfo = $this->handler->calculatePrice($product, 2.0);
    
    // 标准实现只返回基础价格，不包含折扣
    expect($priceInfo['price'])->toBe(1998.00); // 999 * 2
    expect($priceInfo['origin_price'])->toBe(2598.00); // 1299 * 2
    expect($priceInfo['unit_id'])->toBeNull();
    expect($priceInfo['metadata'])->toMatchArray([
        'unit_price' => 999.00,
        'origin_unit_price' => 1299.00,
        'quantity' => 2.0,
    ]);
});

test('上下文不影响基础价格计算', function () {
    $product = new TestHandlerProduct(1, 'iPhone 15', 1000.00, 1000.00);
    
    $context = TransactionContext::make()
        ->withParameters(['member_discount' => 0.1]) // 这个参数不会被使用
        ->fromCampaign('summer_sale', ['discount' => 0.2]); // 这个也不会被使用
    
    $priceInfo = $this->handler->calculatePrice($product, 1.0, $context);
    
    // 价格保持不变，因为折扣应该在 TransactionPreview 中通过 AmountModifier 处理
    expect($priceInfo['price'])->toBe(1000.00);
    expect($priceInfo['origin_price'])->toBe(1000.00);
});

test('可以格式化价格', function () {
    expect($this->handler->formatPrice(999.99))->toBe('¥999.99');
    expect($this->handler->formatPrice(1234567.89))->toBe('¥1,234,567.89');
    
    // 自定义格式
    $options = [
        'prefix' => '$',
        'decimals' => 0,
        'thousands_separator' => ''
    ];
    expect($this->handler->formatPrice(999.99, null, $options))->toBe('$1000');
});

test('可以验证可交易对象', function () {
    $product = new TestHandlerProduct(1, 'iPhone 15', 999.00, 1299.00, 10.0);
    
    // 正常情况
    $result = $this->handler->validateTradable($product, 5.0);
    expect($result['valid'])->toBeTrue();
    expect($result['errors'])->toBeEmpty();
    
    // 库存不足
    $result = $this->handler->validateTradable($product, 20.0);
    expect($result['valid'])->toBeFalse();
    expect($result['errors'])->toContain('库存不足');
    
    // 商品不可用
    $product->available = false;
    $result = $this->handler->validateTradable($product, 1.0);
    expect($result['valid'])->toBeFalse();
    expect($result['errors'])->toContain('商品暂时不可用');
});

test('可以验证购买数量限制', function () {
    $product = new TestHandlerProduct(1, 'iPhone 15', 999.00, 1299.00);
    
    $context = TransactionContext::make()
        ->withParameters([
            'max_quantity' => 5,
            'min_quantity' => 2
        ]);
    
    // 低于最小数量
    $result = $this->handler->validateTradable($product, 1.0, $context);
    expect($result['valid'])->toBeFalse();
    expect($result['errors'])->toContain('未达到最小购买数量（2）');
    
    // 超过最大数量
    $result = $this->handler->validateTradable($product, 10.0, $context);
    expect($result['valid'])->toBeFalse();
    expect($result['errors'])->toContain('超过最大购买数量限制（5）');
    
    // 正常范围
    $result = $this->handler->validateTradable($product, 3.0, $context);
    expect($result['valid'])->toBeTrue();
});

test('可以获取展示信息', function () {
    $product = new TestHandlerProduct(1, 'iPhone 15', 999.00, 1299.00);
    
    $info = $this->handler->getDisplayInfo($product);
    
    expect($info)->toMatchArray([
        'id' => 1,
        'type' => 'test_product',
        'name' => 'iPhone 15',
        'description' => 'Test product: iPhone 15',
        'price' => 999.00,
        'origin_price' => 1299.00,
        'price_formatted' => '¥999.00',
        'origin_price_formatted' => '¥1,299.00',
        'available' => true,
        'provider' => ['name' => 'Test Provider', 'id' => 1],
    ]);
    
    // 不再包含折扣信息
    expect($info)->not->toHaveKey('discount');
});

test('交易支付后会记录日志', function () {
    $product = new TestHandlerProduct(1, 'iPhone 15', 999.00, 1299.00);
    
    // 创建一个完整的交易
    $transaction = TradeTransaction::create([
        'owner_id' => 1,
        'owner_type' => hash_code('test_user'),
        'handler_id' => hash_code('standard'),
        'amount' => 1998.00,
        'origin_amount' => 2598.00,
        'status' => 1,
        'multi_tradables' => false,
        'transaction_number' => 'TEST001',
    ]);
    
    // 使用 Log facade 监听
    Log::shouldReceive('info')
        ->once()
        ->with('Transaction paid for tradable', \Mockery::type('array'));
    
    $this->handler->afterTransactionPaid($product, $transaction, 2.0, ['method' => 'alipay']);
});
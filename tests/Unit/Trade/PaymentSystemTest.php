<?php

use Dybasedev\LunaPrototype\Foundation\Configuration\Repository;
use Dybasedev\LunaPrototype\Trade\Payment\StandardPaymentProvider;
use Dybasedev\LunaPrototype\Trade\Payment\PaymentConfiguration;
use Dybasedev\LunaPrototype\Trade\Payment\PaymentStatus;
use Dybasedev\LunaPrototype\Trade\Payment\PaymentResult;
use Examples\Trade\MockThirdPartyPayment;
use Dybasedev\LunaPrototype\Trade\Payment\PaymentMethodConfigurationRepository;

it('可以创建支付提供者并注册支付方式', function () {
    $provider = new StandardPaymentProvider();
    
    $mockPayment = new MockThirdPartyPayment([
        'name' => 'mock',
        'display_name' => '模拟支付',
        'min_amount' => 1.0,
        'max_amount' => 10000.0,
    ]);
    
    $provider->register($mockPayment);
    
    expect($provider->has('mock'))->toBeTrue();
    expect($provider->get('mock'))->toBe($mockPayment);
});

it('支付配置使用 Repository 管理', function () {
    $config = new PaymentMethodConfigurationRepository([
        'name' => 'test',
        'display_name' => '测试支付',
        'api_key' => 'secret_key',
        'discount_rate' => 5.5,
        'require_password' => true,
    ]);
    
    expect($config->getName())->toBe('test');
    expect($config->getDisplayName())->toBe('测试支付');
    expect($config->getDiscountRate())->toBe(5.5);
    expect($config->requiresPassword())->toBeTrue();
    
    // 敏感信息应该被隐藏
    $config->hideSensitiveData();
    $array = $config->toArray();
    expect($array)->not->toHaveKey('api_key');
});

it('PaymentResult 使用枚举管理状态', function () {
    $result = PaymentResult::success([
        'payment_no' => 'PAY123',
        'amount' => 100.0,
    ]);
    
    expect($result->getStatus())->toBe(PaymentStatus::Success);
    expect($result->isPaid())->toBeTrue();
    expect($result->isSuccess())->toBeTrue();
    
    $pendingResult = PaymentResult::pending('https://pay.example.com');
    expect($pendingResult->getStatus())->toBe(PaymentStatus::Pending);
    expect($pendingResult->isPending())->toBeTrue();
    expect($pendingResult->needsRedirect())->toBeTrue();
    
    $array = $result->toArray();
    expect($array['status'])->toBe('success');
    expect($array['status_name'])->toBe('支付成功');
});

it('支付配置构建器可以正确构建支付提供者', function () {
    $configuration = PaymentConfiguration::create()
        ->setGlobalConfig([
            'test_mode' => true,
            'timeout' => 30,
        ])
        ->registerMethod('mock', MockThirdPartyPayment::class, [
            'name' => 'mock',
            'display_name' => '模拟支付',
        ])
        ->setDefaultMethod('mock');
    
    $provider = $configuration->build();
    
    expect($provider)->toBeInstanceOf(StandardPaymentProvider::class);
    expect($provider->has('mock'))->toBeTrue();
    expect($provider->getDefaultName())->toBe('mock');
    
    $config = $provider->getConfiguration();
    expect($config['test_mode'])->toBeTrue();
    expect($config['timeout'])->toBe(30);
});

it('支付方式优先级排序正确', function () {
    $provider = new StandardPaymentProvider();
    
    $provider->register(new MockThirdPartyPayment(['name' => 'payment1']));
    $provider->register(new MockThirdPartyPayment(['name' => 'payment2']));
    $provider->register(new MockThirdPartyPayment(['name' => 'payment3']));
    
    $provider->setPriority('payment1', 10);
    $provider->setPriority('payment2', 30);
    $provider->setPriority('payment3', 20);
    
    $sorted = $provider->getSorted();
    $names = array_keys($sorted);
    
    expect($names)->toBe(['payment2', 'payment3', 'payment1']);
});
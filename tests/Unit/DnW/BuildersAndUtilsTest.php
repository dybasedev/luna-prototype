<?php

use Dybasedev\LunaPrototype\DnW\Builders\DepositOptionsBuilder;
use Dybasedev\LunaPrototype\DnW\Builders\WithdrawOptionsBuilder;
use Dybasedev\LunaPrototype\DnW\Utils\SignatureValidator;
use Dybasedev\LunaPrototype\DnW\Utils\HttpClient;
use Dybasedev\LunaPrototype\DnW\PaymentMethods;
use Dybasedev\LunaPrototype\DnW\TransactionSpecialMark;
use Illuminate\Support\Facades\Http;

it('DepositOptionsBuilder 正确构建选项', function () {
    $builder = new DepositOptionsBuilder();
    
    $options = $builder
        ->currencyId(1)
        ->originId(123)
        ->originType('order')
        ->specialMark(TransactionSpecialMark::Test)
        ->returnUrl('https://example.com/return')
        ->notifyUrl('https://example.com/notify')
        ->clientIp('192.168.1.1')
        ->userAgent('Mozilla/5.0')
        ->addExtraData('order_no', 'ORD123')
        ->addExtraData('user_level', 'VIP')
        ->build();
    
    expect($options)->toBeArray();
    expect($options['currency_id'])->toBe(1);
    expect($options['origin_id'])->toBe(123);
    expect($options['origin_type'])->toBe('order');
    expect($options['special_mark'])->toBe(TransactionSpecialMark::Test->getCode());
    expect($options['extra_data']['return_url'])->toBe('https://example.com/return');
    expect($options['extra_data']['notify_url'])->toBe('https://example.com/notify');
    expect($options['extra_data']['client_ip'])->toBe('192.168.1.1');
    expect($options['extra_data']['user_agent'])->toBe('Mozilla/5.0');
    expect($options['extra_data']['order_no'])->toBe('ORD123');
    expect($options['extra_data']['user_level'])->toBe('VIP');
});

it('WithdrawOptionsBuilder 正确构建选项', function () {
    $builder = new WithdrawOptionsBuilder();
    
    $options = $builder
        ->currencyId(1)
        ->bindingId(456)
        ->financialAccount('6222021234567890', '张三', '工商银行')
        ->withdrawReason('日常提现')
        ->skipReview()
        ->highPriority()
        ->notifyUrl('https://example.com/withdraw/notify')
        ->fundPasswordVerified()
        ->expectedArrivalTime('2024-01-01 12:00:00')
        ->build();
    
    expect($options)->toBeArray();
    expect($options['currency_id'])->toBe(1);
    expect($options['extra_data']['binding_id'])->toBe(456);
    expect($options['extra_data']['withdraw_account']['type'])->toBe('financial');
    expect($options['extra_data']['withdraw_account']['identifier'])->toBe('6222021234567890');
    expect($options['extra_data']['withdraw_account']['holder'])->toBe('张三');
    expect($options['extra_data']['withdraw_account']['institution'])->toBe('工商银行');
    expect($options['extra_data']['withdraw_reason'])->toBe('日常提现');
    expect($options['extra_data']['skip_review'])->toBeTrue();
    expect($options['extra_data']['priority'])->toBe('high');
    expect($options['extra_data']['notify_url'])->toBe('https://example.com/withdraw/notify');
    expect($options['extra_data']['fund_password_verified'])->toBeTrue();
    expect($options['extra_data']['expected_arrival_time'])->toBe('2024-01-01 12:00:00');
});

it('WithdrawOptionsBuilder 支持不同的账户类型', function () {
    $builder = new WithdrawOptionsBuilder();
    
    // 数字钱包
    $options1 = $builder
        ->digitalWallet('user@example.com', '测试用户', '某支付平台')
        ->build();
    
    expect($options1['extra_data']['withdraw_account']['type'])->toBe('digital_wallet');
    expect($options1['extra_data']['withdraw_account']['account'])->toBe('user@example.com');
    expect($options1['extra_data']['withdraw_account']['provider'])->toBe('某支付平台');
    
    // 区块链地址
    $builder2 = new WithdrawOptionsBuilder();
    $options2 = $builder2
        ->blockchainAddress(
            '0x1234567890abcdef',
            'ethereum',
            ['token' => 'USDT', 'contract' => '0xabc']
        )
        ->build();
    
    expect($options2['extra_data']['withdraw_account']['type'])->toBe('blockchain');
    expect($options2['extra_data']['withdraw_account']['address'])->toBe('0x1234567890abcdef');
    expect($options2['extra_data']['withdraw_account']['network'])->toBe('ethereum');
    expect($options2['extra_data']['withdraw_account']['metadata']['token'])->toBe('USDT');
});

it('SignatureValidator 验证签名', function () {
    $validator = new SignatureValidator('test_secret');
    
    $data = [
        'amount' => '100.00',
        'order_no' => 'TEST123',
        'timestamp' => time(),
    ];
    
    // 生成签名
    $signature = $validator->generateSignature($data);
    expect($signature)->toBeString();
    expect(strlen($signature))->toBe(64); // SHA256 产生64位十六进制字符串
    
    // 验证签名
    expect($validator->validateSignature($data, $signature))->toBeTrue();
    
    // 修改数据后验证失败
    $data['amount'] = '200.00';
    expect($validator->validateSignature($data, $signature))->toBeFalse();
    
    // 验证带时间戳的签名
    $timestampedData = ['test' => 'data'];
    $timestampedSignature = $validator->generateSignatureWithTimestamp($timestampedData);
    
    expect($validator->validateSignatureWithTimestamp(
        $timestampedData,
        $timestampedSignature,
        300 // 5分钟有效期
    ))->toBeTrue();
});

it('HttpClient 发送请求', function () {
    // Mock HTTP 响应
    Http::fake([
        'https://api.example.com/test' => Http::response(['success' => true], 200),
        'https://api.example.com/error' => Http::response(['error' => 'Server Error'], 500),
    ]);
    
    $client = new HttpClient([
        'timeout' => 30,
        'headers' => [
            'X-API-Key' => 'test_key',
        ],
    ]);
    
    // 成功的请求
    $response = $client->post('https://api.example.com/test', [
        'data' => 'test',
    ]);
    
    expect($response)->toBeArray();
    expect($response['success'])->toBeTrue();
    
    // 失败的请求
    expect(fn() => $client->post('https://api.example.com/error', []))
        ->toThrow(\Dybasedev\LunaPrototype\Foundation\Exception\LunaException::class);
    
    // 验证请求头
    Http::assertSent(function ($request) {
        return $request->hasHeader('X-API-Key', 'test_key');
    });
});

it('HttpClient 支持表单提交', function () {
    Http::fake([
        'https://api.example.com/form' => Http::response('OK', 200),
    ]);
    
    $client = new HttpClient();
    
    $response = $client->postForm('https://api.example.com/form', [
        'field1' => 'value1',
        'field2' => 'value2',
    ]);
    
    expect($response)->toBe('OK');
    
    // 验证表单数据
    Http::assertSent(function ($request) {
        return $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded') &&
               $request['field1'] === 'value1' &&
               $request['field2'] === 'value2';
    });
});

it('PaymentMethods 提供支付方式信息', function () {
    // 获取所有支付方式
    $all = PaymentMethods::all();
    expect($all)->toBeArray();
    expect($all)->toContain(PaymentMethods::FINANCIAL_ACCOUNT);
    expect($all)->toContain(PaymentMethods::DIGITAL_WALLET);
    expect($all)->toContain(PaymentMethods::BLOCKCHAIN_ADDRESS);
    
    // 验证支付方式
    expect(PaymentMethods::isValid(PaymentMethods::FINANCIAL_ACCOUNT))->toBeTrue();
    expect(PaymentMethods::isValid('invalid_method'))->toBeFalse();
    
    // 获取显示名称
    expect(PaymentMethods::getDisplayName(PaymentMethods::FINANCIAL_ACCOUNT))->toBe('金融机构账户');
    expect(PaymentMethods::getDisplayName(PaymentMethods::DIGITAL_WALLET))->toBe('数字钱包');
    expect(PaymentMethods::getDisplayName('unknown'))->toBe('unknown');
    
    // 获取描述
    expect(PaymentMethods::getDescription(PaymentMethods::BLOCKCHAIN_ADDRESS))
        ->toContain('区块链');
});

it('构建器支持链式调用', function () {
    $builder = new DepositOptionsBuilder();
    
    // 测试链式调用返回自身
    expect($builder->currencyId(1))->toBe($builder);
    expect($builder->fee('10.00'))->toBe($builder);
    expect($builder->originId(123))->toBe($builder);
    
    // 一次性构建
    $options = (new DepositOptionsBuilder())
        ->currencyId(1)
        ->fee('5.00')
        ->originId(999)
        ->originType('invoice')
        ->returnUrl('https://example.com')
        ->clientIp('127.0.0.1')
        ->addExtraData('custom', 'value')
        ->build();
    
    expect($options['currency_id'])->toBe(1);
    expect($options['fee'])->toBe('5.00');
    expect($options['origin_id'])->toBe(999);
    expect($options['origin_type'])->toBe('invoice');
    expect($options['extra_data']['return_url'])->toBe('https://example.com');
    expect($options['extra_data']['client_ip'])->toBe('127.0.0.1');
    expect($options['extra_data']['custom'])->toBe('value');
});

it('SignatureValidator 支持不同的算法', function () {
    // MD5
    $md5Validator = new SignatureValidator('secret', 'md5');
    $md5Signature = $md5Validator->generateSignature(['test' => 'data']);
    expect(strlen($md5Signature))->toBe(32);
    
    // SHA1
    $sha1Validator = new SignatureValidator('secret', 'sha1');
    $sha1Signature = $sha1Validator->generateSignature(['test' => 'data']);
    expect(strlen($sha1Signature))->toBe(40);
    
    // SHA256（默认）
    $sha256Validator = new SignatureValidator('secret');
    $sha256Signature = $sha256Validator->generateSignature(['test' => 'data']);
    expect(strlen($sha256Signature))->toBe(64);
    
    // 不同算法产生不同签名
    expect($md5Signature)->not->toBe($sha1Signature);
    expect($sha1Signature)->not->toBe($sha256Signature);
});
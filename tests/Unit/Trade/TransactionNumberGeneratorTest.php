<?php

use Dybasedev\LunaPrototype\Trade\Standard\StandardTransactionNumberGenerator;
use Dybasedev\LunaPrototype\Trade\Examples\CustomTransactionNumberGenerator;
use Dybasedev\LunaPrototype\Trade\Models\TradeTransaction;

test('标准编号生成器可以生成和解析交易编号', function () {
    $generator = new StandardTransactionNumberGenerator('T', 8, 4);
    
    // 创建一个模拟交易
    $transaction = new TradeTransaction();
    $transaction->id = 123;
    
    // 生成编号
    $number = $generator->generate($transaction);
    
    // 验证格式
    expect($number)->toStartWith('T');
    expect(strlen($number))->toBe(1 + 14 + 8 + 4); // 前缀1 + 时间14 + ID8 + 随机4
    
    // 验证编号
    expect($generator->validate($number))->toBeTrue();
    expect($generator->validate('INVALID'))->toBeFalse();
    
    // 解析ID
    $parsedId = $generator->parseId($number);
    expect($parsedId)->toBe(123);
    
    // 解析时间戳
    $timestamp = $generator->parseTimestamp($number);
    expect($timestamp)->toBeInstanceOf(DateTimeInterface::class);
});

test('标准编号生成器可以处理不同长度的ID', function () {
    $generator = new StandardTransactionNumberGenerator('ORD', 6, 3);
    
    $transaction = new TradeTransaction();
    $transaction->id = 999999;
    
    $number = $generator->generate($transaction);
    
    expect($number)->toStartWith('ORD');
    expect(strlen($number))->toBe(3 + 14 + 6 + 3);
    
    $parsedId = $generator->parseId($number);
    expect($parsedId)->toBe(999999);
});

test('自定义编号生成器可以生成包含业务类型的编号', function () {
    $generator = new CustomTransactionNumberGenerator('ORD');
    
    $transaction = new TradeTransaction();
    $transaction->id = 456;
    
    // 生成标准类型编号
    $number = $generator->generate($transaction, ['business_type' => 'standard']);
    
    expect($number)->toStartWith('ORD');
    expect(strlen($number))->toBe(3 + 4 + 4 + 2 + 6 + 2); // 前缀3 + 年4 + 月日4 + 类型2 + ID6 + 校验2
    
    // 验证编号
    expect($generator->validate($number))->toBeTrue();
    
    // 解析ID
    $parsedId = $generator->parseId($number);
    expect($parsedId)->toBe(456);
    
    // 解析详细信息
    $details = $generator->parseDetails($number);
    expect($details)->toBeArray();
    expect($details['id'])->toBe(456);
    expect($details['business_type'])->toBe('standard');
    expect($details['business_type_code'])->toBe('01');
    expect($details['year'])->toBe(date('Y'));
});

test('自定义编号生成器的校验码可以防止篡改', function () {
    $generator = new CustomTransactionNumberGenerator('ORD');
    
    $transaction = new TradeTransaction();
    $transaction->id = 789;
    
    $number = $generator->generate($transaction);
    
    // 正常编号应该通过验证
    expect($generator->validate($number))->toBeTrue();
    
    // 修改编号中的任意字符应该导致验证失败
    $tampered = substr($number, 0, -3) . '000'; // 修改最后三位
    expect($generator->validate($tampered))->toBeFalse();
    
    // 修改ID部分也应该导致验证失败
    $tampered2 = substr_replace($number, '999999', strlen('ORD') + 4 + 4 + 2, 6);
    expect($generator->validate($tampered2))->toBeFalse();
});

test('编号生成器可以返回格式说明', function () {
    $standard = new StandardTransactionNumberGenerator();
    expect($standard->getFormatDescription())->toContain('YYYYMMDDHHmmss');
    
    $custom = new CustomTransactionNumberGenerator();
    expect($custom->getFormatDescription())->toContain('BusinessType');
});

test('交易组件可以使用自定义编号生成器', function () {
    $configure = \Dybasedev\LunaPrototype\Trade\LunaTradeConfigure::create()
        ->setDefaultTransactionNumberGeneratorClass(CustomTransactionNumberGenerator::class)
        ->build();
    
    expect($configure->getTransactionNumberGenerator())->toBeInstanceOf(CustomTransactionNumberGenerator::class);
});

test('设置默认交易号前缀', function () {
    $configure = \Dybasedev\LunaPrototype\Trade\LunaTradeConfigure::create()
        ->setDefaultTransactionNumberPrefix('CUSTOM')
        ->build();
    
    // boot方法会初始化默认生成器
    $configure->boot(app());
    
    expect($configure->getTransactionNumberGenerator())->toBeInstanceOf(StandardTransactionNumberGenerator::class);
    
    // 测试生成的编号是否使用了设置的前缀
    $transaction = new TradeTransaction();
    $transaction->id = 1;
    
    $number = $configure->getTransactionNumberGenerator()->generate($transaction);
    expect($number)->toStartWith('CUSTOM');
});
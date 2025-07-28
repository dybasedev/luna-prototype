<?php

use Dybasedev\LunaPrototype\DnW\Models\DepositTransaction;
use Dybasedev\LunaPrototype\DnW\Models\WithdrawTransaction;
use Dybasedev\LunaPrototype\DnW\Models\DepositChannel;
use Dybasedev\LunaPrototype\DnW\Models\WithdrawChannel;
use Dybasedev\LunaPrototype\DnW\TransactionStatus;
use Dybasedev\LunaPrototype\DnW\TransactionSpecialMark;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // 加载迁移
    $this->loadMigrationsFrom(__DIR__ . '/../../../src/Foundation/Handler/migrations');
    $this->loadMigrationsFrom(__DIR__ . '/../../../src/DnW/migrations');
    
    // 创建测试处理器
    $this->depositHandler = \Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler::create([
        'id' => 1,
        'name' => 'test_deposit_handler',
        'group_id' => hash_code('dnw'),
        'display_name' => 'Test Deposit Handler',
        'handler' => 'TestDepositHandler',
        'config' => [],
        'enabled' => true,
    ]);
    
    $this->withdrawHandler = \Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler::create([
        'id' => 2,
        'name' => 'test_withdraw_handler',
        'group_id' => hash_code('dnw'),
        'display_name' => 'Test Withdraw Handler',
        'handler' => 'TestWithdrawHandler',
        'config' => [],
        'enabled' => true,
    ]);
});

it('入金交易模型正确计算净金额', function () {
    $transaction = new DepositTransaction([
        'amount' => '1000.00',
        'fee' => '10.00',
    ]);
    
    expect($transaction->getNetAmount())->toBe('990.00');
    
    // 无手续费
    $transaction->fee = '0.00';
    expect($transaction->getNetAmount())->toBe('1000.00');
    
    // 手续费为 null
    $transaction->fee = null;
    expect($transaction->getNetAmount())->toBe('1000.00');
});

it('出金交易模型正确计算净金额', function () {
    $transaction = new WithdrawTransaction([
        'amount' => '1000.00',
        'fee' => '20.00',
    ]);
    
    expect($transaction->getNetAmount())->toBe('980.00');
    
    // 无手续费
    $transaction->fee = '0.00';
    expect($transaction->getNetAmount())->toBe('1000.00');
    
    // 手续费为 null
    $transaction->fee = null;
    expect($transaction->getNetAmount())->toBe('1000.00');
});

it('交易状态枚举正确工作', function () {
    $pending = TransactionStatus::Pending;
    expect($pending->value)->toBe('pending');
    expect($pending->getCode())->toBe(short_hash_code('pending'));
    expect($pending->getDisplayName())->toBe('待处理');
    expect($pending->getColor())->toBe('gray');
    
    $success = TransactionStatus::Success;
    expect($success->value)->toBe('success');
    expect($success->getDisplayName())->toBe('成功');
    expect($success->getColor())->toBe('green');
    
    // 测试从代码获取状态
    $code = TransactionStatus::Processing->getCode();
    $status = TransactionStatus::fromCode($code);
    expect($status)->toBe(TransactionStatus::Processing);
    
    // 测试无效代码
    expect(TransactionStatus::fromCode(999999))->toBeNull();
});

it('特殊标记枚举正确工作', function () {
    $normal = TransactionSpecialMark::Normal;
    expect($normal->value)->toBe('normal');
    expect($normal->getCode())->toBe(0);
    expect($normal->getDisplayName())->toBe('正常');
    
    $test = TransactionSpecialMark::Test;
    expect($test->value)->toBe('test');
    expect($test->getCode())->toBe(1);
    expect($test->getDisplayName())->toBe('测试');
    
    // 测试从代码获取标记
    expect(TransactionSpecialMark::fromCode(0))->toBe(TransactionSpecialMark::Normal);
    expect(TransactionSpecialMark::fromCode(1))->toBe(TransactionSpecialMark::Test);
    expect(TransactionSpecialMark::fromCode(999))->toBeNull();
});

it('交易模型关系正确', function () {
    // 创建渠道
    $depositChannel = DepositChannel::create([
        'id' => hash_code('test_deposit_channel'),
        'name' => 'test_deposit',
        'handler_id' => $this->depositHandler->id,
        'config' => [],
        'is_active' => true,
        'sort' => 0,
    ]);
    
    $withdrawChannel = WithdrawChannel::create([
        'id' => hash_code('test_withdraw_channel'),
        'name' => 'test_withdraw',
        'handler_id' => $this->withdrawHandler->id,
        'config' => [],
        'is_active' => true,
        'sort' => 0,
    ]);
    
    // 创建交易
    $depositTx = DepositTransaction::create([
        'channel_id' => $depositChannel->id,
        'owner_id' => 1,
        'owner_type' => hash_code('test_user'),
        'amount' => '1000.00',
        'fee' => '10.00',
        'currency_id' => 1,
        'status' => TransactionStatus::Success->getCode(),
        'special_mark' => TransactionSpecialMark::Normal->getCode(),
    ]);
    
    $withdrawTx = WithdrawTransaction::create([
        'channel_id' => $withdrawChannel->id,
        'owner_id' => 1,
        'owner_type' => hash_code('test_user'),
        'amount' => '500.00',
        'fee' => '5.00',
        'currency_id' => 1,
        'status' => TransactionStatus::Success->getCode(),
        'special_mark' => TransactionSpecialMark::Normal->getCode(),
    ]);
    
    // 测试关系
    expect($depositTx->channel)->toBeInstanceOf(DepositChannel::class);
    expect($depositTx->channel->id)->toBe($depositChannel->id);
    
    expect($withdrawTx->channel)->toBeInstanceOf(WithdrawChannel::class);
    expect($withdrawTx->channel->id)->toBe($withdrawChannel->id);
});

it('交易作用域正确工作', function () {
    // 创建测试数据
    $channel = DepositChannel::create([
        'id' => hash_code('test_channel'),
        'name' => 'test',
        'handler_id' => $this->depositHandler->id,
        'config' => [],
        'is_active' => true,
        'sort' => 0,
    ]);
    
    // 创建不同状态的交易
    $pendingTx = DepositTransaction::create([
        'channel_id' => $channel->id,
        'owner_id' => 1,
        'owner_type' => hash_code('test_user'),
        'amount' => '100.00',
        'status' => TransactionStatus::Pending->getCode(),
        'special_mark' => TransactionSpecialMark::Normal->getCode(),
    ]);
    
    $successTx = DepositTransaction::create([
        'channel_id' => $channel->id,
        'owner_id' => 1,
        'owner_type' => hash_code('test_user'),
        'amount' => '200.00',
        'status' => TransactionStatus::Success->getCode(),
        'special_mark' => TransactionSpecialMark::Normal->getCode(),
    ]);
    
    $testTx = DepositTransaction::create([
        'channel_id' => $channel->id,
        'owner_id' => 1,
        'owner_type' => hash_code('test_user'),
        'amount' => '300.00',
        'status' => TransactionStatus::Success->getCode(),
        'special_mark' => TransactionSpecialMark::Test->getCode(),
    ]);
    
    // 测试状态作用域
    $pendingTransactions = DepositTransaction::byStatus(TransactionStatus::Pending)->get();
    expect($pendingTransactions)->toHaveCount(1);
    expect($pendingTransactions->first()->id)->toBe($pendingTx->id);
    
    $successTransactions = DepositTransaction::byStatus(TransactionStatus::Success)->get();
    expect($successTransactions)->toHaveCount(2);
    
    // 测试特殊标记作用域
    $normalTransactions = DepositTransaction::normalTransactions()->get();
    expect($normalTransactions)->toHaveCount(2);
    
    $testTransactions = DepositTransaction::testTransactions()->get();
    expect($testTransactions)->toHaveCount(1);
    expect($testTransactions->first()->id)->toBe($testTx->id);
    
    // 测试所有者过滤
    $ownerTransactions = DepositTransaction::where('owner_id', 1)
        ->where('owner_type', hash_code('test_user'))
        ->get();
    expect($ownerTransactions)->toHaveCount(3);
});

it('交易状态转换记录日志', function () {
    $channel = DepositChannel::create([
        'id' => hash_code('test_channel'),
        'name' => 'test',
        'handler_id' => $this->depositHandler->id,
        'config' => [],
        'is_active' => true,
        'sort' => 0,
    ]);
    
    $transaction = DepositTransaction::create([
        'channel_id' => $channel->id,
        'owner_id' => 1,
        'owner_type' => hash_code('test_user'),
        'amount' => '1000.00',
        'status' => TransactionStatus::Pending->getCode(),
        'special_mark' => TransactionSpecialMark::Normal->getCode(),
    ]);
    
    // 转换状态
    $transaction->transitionTo(TransactionStatus::Processing, [
        'operator_id' => 100,
        'remark' => '开始处理',
    ]);
    
    // 验证状态已更新
    expect($transaction->status)->toBe(TransactionStatus::Processing->getCode());
    
    // 验证日志已创建
    $logs = $transaction->logs;
    expect($logs)->toHaveCount(2); // 创建时的初始状态 + 转换
    
    $latestLog = $logs->last();
    expect($latestLog->from_status)->toBe(TransactionStatus::Pending->getCode());
    expect($latestLog->to_status)->toBe(TransactionStatus::Processing->getCode());
    expect($latestLog->operator_id)->toBe(100);
    expect($latestLog->remark)->toBe('开始处理');
});

it('出金交易审核功能正确工作', function () {
    $channel = WithdrawChannel::create([
        'id' => hash_code('test_channel'),
        'name' => 'test',
        'handler_id' => $this->withdrawHandler->id,
        'config' => [],
        'is_active' => true,
        'sort' => 0,
    ]);
    
    $transaction = WithdrawTransaction::create([
        'channel_id' => $channel->id,
        'owner_id' => 1,
        'owner_type' => hash_code('test_user'),
        'amount' => '1000.00',
        'status' => TransactionStatus::Reviewing->getCode(),
        'special_mark' => TransactionSpecialMark::Normal->getCode(),
    ]);
    
    // 测试需要审核
    expect($transaction->needsReview())->toBeTrue();
    
    // 批准
    $transaction->approve([
        'operator_id' => 100,
        'note' => '审核通过',
    ]);
    
    expect($transaction->status)->toBe(TransactionStatus::Processing->getCode());
    
    // 测试拒绝
    $transaction2 = WithdrawTransaction::create([
        'channel_id' => $channel->id,
        'owner_id' => 2,
        'owner_type' => hash_code('test_user'),
        'amount' => '2000.00',
        'status' => TransactionStatus::Reviewing->getCode(),
        'special_mark' => TransactionSpecialMark::Normal->getCode(),
    ]);
    
    $transaction2->reject('余额不足', [
        'operator_id' => 100,
    ]);
    
    expect($transaction2->status)->toBe(TransactionStatus::Rejected->getCode());
});
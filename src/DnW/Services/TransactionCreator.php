<?php

namespace Dybasedev\LunaPrototype\DnW\Services;

use Dybasedev\LunaPrototype\DnW\Models\DepositTransaction;
use Dybasedev\LunaPrototype\DnW\Models\WithdrawTransaction;
use Dybasedev\LunaPrototype\DnW\Models\DepositChannel;
use Dybasedev\LunaPrototype\DnW\Models\WithdrawChannel;
use Dybasedev\LunaPrototype\DnW\TransactionStatus;
use Dybasedev\LunaPrototype\DnW\TransactionSpecialMark;
use Dybasedev\LunaPrototype\DnW\LunaDnWConfigure;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * 交易创建服务
 * 
 * 负责创建和初始化入金/出金交易
 */
class TransactionCreator
{
    /**
     * 构造函数
     */
    public function __construct(
        protected LunaDnWConfigure $configure
    ) {
    }

    /**
     * 创建入金交易
     */
    public function createDepositTransaction(
        DepositChannel $channel,
        Model $owner,
        string $amount,
        array $options = []
    ): DepositTransaction {
        // 验证渠道是否激活
        if (!$channel->is_active) {
            throw LunaException::create('Deposit channel is not active')
                ->withDisplayMessage('入金渠道未激活')
                ->withHttpStatus(400);
        }

        return DB::transaction(function () use ($channel, $owner, $amount, $options) {
            $modelClass = $this->configure->depositTransactionModel;
            
            $transaction = new $modelClass([
                'channel_id' => $channel->id,
                'owner_id' => $owner->getKey(),
                'owner_type' => hash_code($owner->getMorphClass()),
                'amount' => $amount,
                'fee' => $options['fee'] ?? '0',
                'currency_id' => $options['currency_id'] ?? null,
                'external_id' => null,
                'origin_id' => $options['origin_id'] ?? null,
                'origin_type' => isset($options['origin_type']) ? hash_code($options['origin_type']) : null,
                'extra_data' => $options['extra_data'] ?? null,
                'special_mark' => $options['special_mark'] ?? TransactionSpecialMark::Normal->getCode(),
                'status' => TransactionStatus::Pending->getCode(),
            ]);
            
            $transaction->save();
            
            return $transaction;
        });
    }

    /**
     * 创建出金交易
     */
    public function createWithdrawTransaction(
        WithdrawChannel $channel,
        Model $owner,
        string $amount,
        array $options = []
    ): WithdrawTransaction {
        // 验证渠道是否激活
        if (!$channel->is_active) {
            throw LunaException::create('Withdraw channel is not active')
                ->withDisplayMessage('出金渠道未激活')
                ->withHttpStatus(400);
        }

        return DB::transaction(function () use ($channel, $owner, $amount, $options) {
            $modelClass = $this->configure->withdrawTransactionModel;
            
            $transaction = new $modelClass([
                'channel_id' => $channel->id,
                'owner_id' => $owner->getKey(),
                'owner_type' => hash_code($owner->getMorphClass()),
                'amount' => $amount,
                'fee' => $options['fee'] ?? '0',
                'currency_id' => $options['currency_id'] ?? null,
                'external_id' => null,
                'origin_id' => $options['origin_id'] ?? null,
                'origin_type' => isset($options['origin_type']) ? hash_code($options['origin_type']) : null,
                'extra_data' => $options['extra_data'] ?? null,
                'special_mark' => $options['special_mark'] ?? TransactionSpecialMark::Normal->getCode(),
                'status' => TransactionStatus::Pending->getCode(),
            ]);
            
            // 检查是否需要审核
            if ($this->configure->enableWithdrawReview && 
                $amount >= $this->configure->withdrawReviewThreshold) {
                $transaction->status = TransactionStatus::Reviewing->getCode();
            }
            
            $transaction->save();
            
            return $transaction;
        });
    }

    /**
     * 计算入金手续费
     */
    public function calculateDepositFee(DepositChannel $channel, string $amount): string
    {
        if (!$channel->handler || !$channel->handler->config) {
            return '0';
        }

        $config = $channel->handler->config;
        $feeRate = $config['fee_rate'] ?? 0;
        $fixedFee = $config['fixed_fee'] ?? 0;

        $fee = ($amount * $feeRate / 100) + $fixedFee;

        return number_format($fee, 2, '.', '');
    }

    /**
     * 计算出金手续费
     */
    public function calculateWithdrawFee(WithdrawChannel $channel, string $amount): string
    {
        if (!$channel->handler || !$channel->handler->config) {
            return '0';
        }

        $config = $channel->handler->config;
        $feeRate = $config['fee_rate'] ?? 0;
        $fixedFee = $config['fixed_fee'] ?? 0;

        $fee = ($amount * $feeRate / 100) + $fixedFee;

        return number_format($fee, 2, '.', '');
    }

    /**
     * 验证交易金额
     */
    public function validateAmount(DepositChannel|WithdrawChannel $channel, string $amount): bool
    {
        if (!$channel->handler || !$channel->handler->config) {
            return true;
        }

        $config = $channel->handler->config;
        $enableFixedLimit = $config['enable_fixed_limit'] ?? false;
        $enableRangeLimit = $config['enable_range_limit'] ?? false;

        // 如果没有开启任何限制，则直接返回 true
        if (!$enableRangeLimit && !$enableFixedLimit) {
            return true;
        }

        $allow = false;
        
        if ($enableFixedLimit) {
            $fixedLimits = $config['fixed_limit'] ?? [];
            $allow = in_array($amount, array_map('strval', $fixedLimits));
        }

        if (!$allow && $enableRangeLimit) {
            $rangeLimit = $config['range_limit'] ?? [];
            
            if (count($rangeLimit) === 1) {
                $allow = $amount >= $rangeLimit[0];
            } elseif (count($rangeLimit) === 2) {
                $allow = $amount >= $rangeLimit[0] && $amount <= $rangeLimit[1];
            }
        }

        return $allow;
    }
}
<?php

namespace Dybasedev\LunaPrototype\DnW\Traits;

use Dybasedev\LunaPrototype\DnW\Models\DepositTransaction;
use Dybasedev\LunaPrototype\DnW\Models\WithdrawTransaction;
use Dybasedev\LunaPrototype\DnW\Models\DepositBinding;
use Dybasedev\LunaPrototype\DnW\Models\WithdrawBinding;
use Dybasedev\LunaPrototype\DnW\LunaDnW;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * 出入金功能 Trait
 * 
 * 为模型添加出入金相关功能
 */
trait HasDnW
{
    /**
     * 获取入金交易记录
     */
    public function depositTransactions(): MorphMany
    {
        $modelClass = app(\Dybasedev\LunaPrototype\DnW\LunaDnWConfigure::class)->depositTransactionModel;
        return $this->morphMany($modelClass, 'owner');
    }

    /**
     * 获取出金交易记录
     */
    public function withdrawTransactions(): MorphMany
    {
        $modelClass = app(\Dybasedev\LunaPrototype\DnW\LunaDnWConfigure::class)->withdrawTransactionModel;
        return $this->morphMany($modelClass, 'owner');
    }

    /**
     * 获取入金绑定
     */
    public function depositBindings(): MorphMany
    {
        $modelClass = app(\Dybasedev\LunaPrototype\DnW\LunaDnWConfigure::class)->depositBindingModel;
        return $this->morphMany($modelClass, 'owner');
    }

    /**
     * 获取出金绑定
     */
    public function withdrawBindings(): MorphMany
    {
        $modelClass = app(\Dybasedev\LunaPrototype\DnW\LunaDnWConfigure::class)->withdrawBindingModel;
        return $this->morphMany($modelClass, 'owner');
    }

    /**
     * 创建入金交易
     */
    public function createDepositTransaction($channel, float $amount, array $data = []): DepositTransaction
    {
        $dnw = app(LunaDnW::class);
        
        if (is_numeric($channel)) {
            $channelModel = app(\Dybasedev\LunaPrototype\DnW\LunaDnWConfigure::class)->depositChannelModel;
            $channel = $channelModel::findOrFail($channel);
        }
        
        return $dnw->createDepositTransaction($this, $channel, $amount, $data);
    }

    /**
     * 创建出金交易
     */
    public function createWithdrawTransaction($channel, float $amount, array $data = []): WithdrawTransaction
    {
        $dnw = app(LunaDnW::class);
        
        if (is_numeric($channel)) {
            $channelModel = app(\Dybasedev\LunaPrototype\DnW\LunaDnWConfigure::class)->withdrawChannelModel;
            $channel = $channelModel::findOrFail($channel);
        }
        
        return $dnw->createWithdrawTransaction($this, $channel, $amount, $data);
    }

    /**
     * 获取默认入金绑定
     */
    public function getDefaultDepositBinding(?int $channelId = null): ?DepositBinding
    {
        $query = $this->depositBindings()->where('is_active', true)->where('is_default', true);
        
        if ($channelId) {
            $query->where('channel_id', $channelId);
        }
        
        return $query->first();
    }

    /**
     * 获取默认出金绑定
     */
    public function getDefaultWithdrawBinding(?int $channelId = null): ?WithdrawBinding
    {
        $query = $this->withdrawBindings()->where('is_active', true)->where('is_default', true);
        
        if ($channelId) {
            $query->where('channel_id', $channelId);
        }
        
        return $query->first();
    }

    /**
     * 获取入金统计
     */
    public function getDepositStatistics(array $filters = []): array
    {
        $query = $this->depositTransactions()->completed();
        
        if (isset($filters['start_date'])) {
            $query->where('completed_at', '>=', $filters['start_date']);
        }
        
        if (isset($filters['end_date'])) {
            $query->where('completed_at', '<=', $filters['end_date']);
        }
        
        if (isset($filters['currency'])) {
            $query->where('currency', $filters['currency']);
        }
        
        return [
            'total_amount' => $query->sum('amount'),
            'total_fee' => $query->sum('fee'),
            'total_count' => $query->count(),
            'net_amount' => $query->sum(\DB::raw('amount - fee')),
        ];
    }

    /**
     * 获取出金统计
     */
    public function getWithdrawStatistics(array $filters = []): array
    {
        $query = $this->withdrawTransactions()->completed();
        
        if (isset($filters['start_date'])) {
            $query->where('completed_at', '>=', $filters['start_date']);
        }
        
        if (isset($filters['end_date'])) {
            $query->where('completed_at', '<=', $filters['end_date']);
        }
        
        if (isset($filters['currency'])) {
            $query->where('currency', $filters['currency']);
        }
        
        return [
            'total_amount' => $query->sum('amount'),
            'total_fee' => $query->sum('fee'),
            'total_count' => $query->count(),
            'net_amount' => $query->sum(\DB::raw('amount - fee')),
        ];
    }

    /**
     * 检查是否有待处理的入金交易
     */
    public function hasPendingDepositTransactions(): bool
    {
        return $this->depositTransactions()->pending()->exists();
    }

    /**
     * 检查是否有待处理的出金交易
     */
    public function hasPendingWithdrawTransactions(): bool
    {
        return $this->withdrawTransactions()->pending()->exists();
    }
}
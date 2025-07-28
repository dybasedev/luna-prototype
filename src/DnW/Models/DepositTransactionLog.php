<?php

namespace Dybasedev\LunaPrototype\DnW\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 入金交易日志模型
 * 
 * @property int $id
 * @property string $transaction_id 交易ID
 * @property string|null $from_status 原状态
 * @property string $to_status 新状态
 * @property array|null $payload 载荷数据
 * @property array|null $extra_data 附带数据
 * @property string|null $operator_type 操作者类型
 * @property string|null $operator_id 操作者ID
 * @property string|null $remark 备注
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class DepositTransactionLog extends Model
{
    /**
     * 表名
     */
    protected $table = 'luna_deposit_transaction_logs';

    /**
     * 可填充字段
     */
    protected $fillable = [
        'transaction_id',
        'from_status',
        'to_status',
        'payload',
        'extra_data',
        'operator_type',
        'operator_id',
        'remark',
    ];

    /**
     * 类型转换
     */
    protected $casts = [
        'payload' => 'array',
        'extra_data' => 'array',
    ];

    /**
     * 获取所属交易
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(DepositTransaction::class, 'transaction_id');
    }

    /**
     * 获取操作者描述
     */
    public function getOperatorDescription(): string
    {
        if (!$this->operator_type || !$this->operator_id) {
            return '系统';
        }

        return "{$this->operator_type}:{$this->operator_id}";
    }

    /**
     * 获取状态变更描述
     */
    public function getStatusChangeDescription(): string
    {
        if ($this->from_status) {
            return "{$this->from_status} → {$this->to_status}";
        }

        return "→ {$this->to_status}";
    }
}
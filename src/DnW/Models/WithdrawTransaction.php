<?php

namespace Dybasedev\LunaPrototype\DnW\Models;

use Dybasedev\LunaPrototype\DnW\TransactionStatus;
use Dybasedev\LunaPrototype\DnW\TransactionSpecialMark;
use Dybasedev\LunaPrototype\DnW\LunaDnWConfigure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * 出金交易模型
 * 
 * @property int $id
 * @property int $channel_id 渠道ID
 * @property int $owner_id 所有者ID
 * @property int $owner_type 所有者类型
 * @property float $amount 金额
 * @property float $fee 手续费
 * @property int|null $currency_id 货币ID
 * @property string|null $external_id 外部交易ID
 * @property int|null $origin_id 来源ID
 * @property int|null $origin_type 来源类型
 * @property int $status 状态
 * @property string|null $reject_reason 拒绝原因
 * @property array|null $extra_data 额外数据
 * @property \Carbon\Carbon|null $reviewed_at 审核时间
 * @property \Carbon\Carbon|null $completed_at 完成时间
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class WithdrawTransaction extends Model
{
    /**
     * 表名
     */
    protected $table = 'luna_withdraw_transactions';

    /**
     * 可填充字段
     */
    protected $fillable = [
        'channel_id',
        'owner_id',
        'owner_type',
        'amount',
        'fee',
        'currency_id',
        'external_id',
        'origin_id',
        'origin_type',
        'status',
        'special_mark',
        'reject_reason',
        'extra_data',
        'reviewed_at',
        'completed_at',
    ];

    /**
     * 类型转换
     */
    protected $casts = [
        'amount' => 'decimal:8',
        'fee' => 'decimal:8',
        'extra_data' => 'array',
        'reviewed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * 启动模型
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (WithdrawTransaction $transaction) {
            // 设置默认状态
            if (is_null($transaction->status)) {
                $transaction->status = TransactionStatus::Pending->getCode();
            }
            
            // 设置所有者类型
            // Note: owner_type is already set during creation as hash_code of the morph class
            
            // 设置来源类型
            // Note: origin_type is already set during creation as hash_code of the origin type string
        });

        static::created(function (WithdrawTransaction $transaction) {
            $transaction->recordLog(null, TransactionStatus::Pending->getCode(), '交易创建');
        });
    }

    /**
     * 获取渠道
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(WithdrawChannel::class, 'channel_id');
    }

    /**
     * 获取所有者
     */
    public function owner(): MorphTo
    {
        return $this->morphTo(
            name: 'owner',
            type: 'owner_type',
            id: 'owner_id'
        );
    }

    /**
     * 获取来源
     */
    public function origin(): MorphTo
    {
        return $this->morphTo(
            name: 'origin',
            type: 'origin_type',
            id: 'origin_id'
        );
    }

    /**
     * 获取日志
     */
    public function logs(): HasMany
    {
        return $this->hasMany(WithdrawTransactionLog::class, 'transaction_id')->orderBy('created_at');
    }

    /**
     * 获取实际出金金额
     */
    public function getNetAmount(): string
    {
        $amount = (float)$this->amount;
        $fee = (float)($this->fee ?? 0);
        return number_format($amount - $fee, 2, '.', '');
    }

    /**
     * 获取状态枚举
     */
    public function getStatus(): ?TransactionStatus
    {
        return TransactionStatus::fromCode($this->status);
    }

    /**
     * 设置状态
     */
    public function setStatusAttribute($value): void
    {
        if ($value instanceof TransactionStatus) {
            $this->attributes['status'] = $value->getCode();
        } else {
            $this->attributes['status'] = $value;
        }
    }

    /**
     * 状态转换
     */
    public function transitionTo(TransactionStatus $status, array $data = []): bool
    {
        $currentStatus = $this->getStatus();
        
        if ($currentStatus === $status) {
            return true;
        }

        // 验证状态转换是否合法
        if (!$this->canTransitionTo($status)) {
            return false;
        }

        $fromStatusCode = $this->status;
        $this->status = $status;
        
        // 处理特定状态的逻辑
        switch ($status) {
            case TransactionStatus::Reviewing:
                // 进入审核状态
                break;
                
            case TransactionStatus::Processing:
                $this->reviewed_at = now();
                break;
                
            case TransactionStatus::Success:
                $this->completed_at = now();
                break;
                
            case TransactionStatus::Rejected:
                $this->reviewed_at = now();
                $this->reject_reason = $data['reject_reason'] ?? null;
                break;
        }
        
        $this->save();

        // 记录日志
        $this->recordLog($fromStatusCode, $status->getCode(), $data['remark'] ?? null, $data);

        return true;
    }

    /**
     * 检查是否可以转换到指定状态
     */
    public function canTransitionTo(TransactionStatus $status): bool
    {
        $currentStatus = $this->getStatus();
        
        if (!$currentStatus) {
            return false;
        }

        $transitions = [
            TransactionStatus::Pending->value => [
                TransactionStatus::Reviewing->value,
                TransactionStatus::Processing->value,
                TransactionStatus::Cancelled->value
            ],
            TransactionStatus::Reviewing->value => [
                TransactionStatus::Processing->value,
                TransactionStatus::Rejected->value,
                TransactionStatus::Cancelled->value
            ],
            TransactionStatus::Processing->value => [
                TransactionStatus::Success->value,
                TransactionStatus::Failed->value
            ],
            TransactionStatus::Success->value => [],
            TransactionStatus::Failed->value => [],
            TransactionStatus::Rejected->value => [],
            TransactionStatus::Cancelled->value => [],
        ];

        return in_array($status->value, $transitions[$currentStatus->value] ?? []);
    }

    /**
     * 记录日志
     */
    protected function recordLog(?int $fromStatus, int $toStatus, ?string $remark = null, array $data = []): void
    {
        $configure = app(LunaDnWConfigure::class);
        
        if (!$configure->enableTransactionLog) {
            return;
        }
        
        $logModelClass = $configure->withdrawTransactionLogModel;
        
        $logModelClass::create([
            'transaction_id' => $this->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'event_id' => $data['event_id'] ?? null,
            'payload' => $data['payload'] ?? null,
            'operator_id' => $data['operator_id'] ?? null,
            'operator_type' => isset($data['operator_type']) ? hash_code($data['operator_type']) : null,
            'remark' => $remark,
        ]);
    }

    /**
     * 提交审核
     */
    public function submitForReview(array $data = []): bool
    {
        return $this->transitionTo(TransactionStatus::Reviewing, $data);
    }

    /**
     * 审核通过
     */
    public function approve(array $data = []): bool
    {
        return $this->transitionTo(TransactionStatus::Processing, $data);
    }

    /**
     * 审核拒绝
     */
    public function reject(string $reason, array $data = []): bool
    {
        $data['reject_reason'] = $reason;
        return $this->transitionTo(TransactionStatus::Rejected, $data);
    }

    /**
     * 标记为处理中
     */
    public function markAsProcessing(array $data = []): bool
    {
        return $this->transitionTo(TransactionStatus::Processing, $data);
    }

    /**
     * 标记为成功
     */
    public function markAsSuccess(array $data = []): bool
    {
        return $this->transitionTo(TransactionStatus::Success, $data);
    }

    /**
     * 标记为失败
     */
    public function markAsFailed(array $data = []): bool
    {
        return $this->transitionTo(TransactionStatus::Failed, $data);
    }

    /**
     * 取消交易
     */
    public function cancel(array $data = []): bool
    {
        return $this->transitionTo(TransactionStatus::Cancelled, $data);
    }

    /**
     * 是否需要审核
     */
    public function needsReview(): bool
    {
        return $this->getStatus() === TransactionStatus::Reviewing;
    }

    /**
     * 作用域：按状态
     */
    public function scopeByStatus($query, TransactionStatus $status)
    {
        return $query->where('status', $status->getCode());
    }

    /**
     * 作用域：已完成
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', TransactionStatus::Success->getCode());
    }

    /**
     * 作用域：待处理
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', [
            TransactionStatus::Pending->getCode(),
            TransactionStatus::Reviewing->getCode(),
            TransactionStatus::Processing->getCode()
        ]);
    }

    /**
     * 作用域：待审核
     */
    public function scopeNeedsReview($query)
    {
        return $query->where('status', TransactionStatus::Reviewing->getCode());
    }

    /**
     * 获取特殊标记
     */
    public function getSpecialMark(): ?TransactionSpecialMark
    {
        return TransactionSpecialMark::fromCode($this->special_mark);
    }

    /**
     * 设置特殊标记
     */
    public function setSpecialMark(TransactionSpecialMark $mark): void
    {
        $this->special_mark = $mark->getCode();
    }

    /**
     * 是否为测试交易
     */
    public function isTestTransaction(): bool
    {
        $mark = $this->getSpecialMark();
        return $mark && $mark->isTestType();
    }

    /**
     * 是否可以完成
     */
    public function canComplete(): bool
    {
        $currentStatus = $this->getStatus();
        return $currentStatus === TransactionStatus::Processing;
    }

    /**
     * 是否可以拒绝
     */
    public function canReject(): bool
    {
        $currentStatus = $this->getStatus();
        return in_array($currentStatus, [
            TransactionStatus::Reviewing,
            TransactionStatus::Processing,
        ]);
    }

    /**
     * 作用域：测试交易
     */
    public function scopeTestTransactions($query)
    {
        $testCodes = [
            TransactionSpecialMark::Test->getCode(),
            TransactionSpecialMark::Development->getCode(),
            TransactionSpecialMark::Demo->getCode(),
        ];
        return $query->whereIn('special_mark', $testCodes);
    }

    /**
     * 作用域：正常交易
     */
    public function scopeNormalTransactions($query)
    {
        return $query->where('special_mark', TransactionSpecialMark::Normal->getCode());
    }
}
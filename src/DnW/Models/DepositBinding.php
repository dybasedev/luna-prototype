<?php

namespace Dybasedev\LunaPrototype\DnW\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * 入金绑定模型
 * 
 * @property int $id
 * @property int $channel_id 渠道ID
 * @property int $owner_type 所有者类型
 * @property int $owner_id 所有者ID
 * @property string $channel 渠道标识，如 financial_account、blockchain_address、digital_wallet 等
 * @property string $account 渠道对应账户，如账户标识符、地址、钱包账号等
 * @property string|null $account_name 持有者账户名称
 * @property string $channel_name 渠道名称，例如金融机构名称、区块链网络名称、数字钱包服务商等
 * @property string $channel_provider 渠道的供应者，例如金融网络类型、区块链网络、数字钱包平台等
 * @property array|null $extra_info 额外信息
 * @property bool $is_active 是否启用
 * @property bool $is_default 是否默认
 * @property int $sort 排序
 * @property \Carbon\Carbon|null $verified_at 验证时间
 * @property array|null $metadata 元数据
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class DepositBinding extends Model
{
    /**
     * 表名
     */
    protected $table = 'luna_deposit_bindings';

    /**
     * 可填充字段
     */
    protected $fillable = [
        'channel_id',
        'owner_type',
        'owner_id',
        'channel',
        'account',
        'account_name',
        'channel_name',
        'channel_provider',
        'extra_info',
        'is_active',
        'is_default',
        'sort',
        'verified_at',
        'metadata',
    ];

    /**
     * 类型转换
     */
    protected $casts = [
        'extra_info' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'verified_at' => 'datetime',
        'sort' => 'integer',
    ];

    /**
     * 启动模型
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (DepositBinding $binding) {
            // 设置所有者类型
            if ($binding->owner && !$binding->owner_type) {
                $binding->owner_type = hash_code($binding->owner->getMorphClass());
            }
        });
    }

    /**
     * 获取渠道
     */
    public function channelModel(): BelongsTo
    {
        return $this->belongsTo(DepositChannel::class, 'channel_id');
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
     * 获取账户显示名称
     */
    public function getDisplayName(): string
    {
        if ($this->account_name) {
            return $this->account_name;
        }

        return $this->getMaskedAccount();
    }

    /**
     * 获取脱敏的账户号码
     */
    public function getMaskedAccount(): string
    {
        if (!$this->account) {
            return '****';
        }

        $length = strlen($this->account);
        
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        if ($length <= 8) {
            return substr($this->account, 0, 2) . str_repeat('*', $length - 4) . substr($this->account, -2);
        }

        return substr($this->account, 0, 4) . str_repeat('*', $length - 8) . substr($this->account, -4);
    }

    /**
     * 激活绑定
     */
    public function activate(): self
    {
        $this->is_active = true;
        $this->save();
        
        return $this;
    }

    /**
     * 停用绑定
     */
    public function deactivate(): self
    {
        $this->is_active = false;
        $this->is_default = false;
        $this->save();
        
        return $this;
    }

    /**
     * 设为默认
     */
    public function setAsDefault(): self
    {
        // 清除其他默认绑定
        static::where('owner_type', $this->owner_type)
            ->where('owner_id', $this->owner_id)
            ->where('channel_id', $this->channel_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);
        
        $this->is_default = true;
        $this->is_active = true;
        $this->save();
        
        return $this;
    }

    /**
     * 验证账户
     */
    public function verify(array $data = []): self
    {
        $this->verified_at = now();
        
        if (isset($data['metadata'])) {
            $this->metadata = array_merge($this->metadata ?? [], $data['metadata']);
        }
        
        $this->save();
        
        return $this;
    }

    /**
     * 是否已验证
     */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * 作用域：激活的
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * 作用域：已验证的
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }

    /**
     * 作用域：按渠道标识
     */
    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * 作用域：按排序
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort')->orderBy('id');
    }
}
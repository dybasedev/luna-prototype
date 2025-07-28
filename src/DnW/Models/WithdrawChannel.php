<?php

namespace Dybasedev\LunaPrototype\DnW\Models;

use Dybasedev\LunaPrototype\Foundation\NamedId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler;

/**
 * 出金渠道模型
 * 
 * @property int $id
 * @property string $name 渠道名称
 * @property int $handler_id 处理器ID
 * @property array|null $config 渠道配置
 * @property bool $is_active 是否启用
 * @property int $sort 排序
 * @property array|null $metadata 元数据
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class WithdrawChannel extends Model
{
    use NamedId;

    /**
     * 表名
     */
    protected $table = 'luna_withdraw_channels';

    /**
     * 主键类型
     */
    protected $keyType = 'int';

    /**
     * 是否自增
     */
    public $incrementing = false;

    /**
     * 可填充字段
     */
    protected $fillable = [
        'name',
        'handler_id',
        'config',
        'is_active',
        'sort',
        'metadata',
    ];

    /**
     * 类型转换
     */
    protected $casts = [
        'config' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];

    /**
     * 获取处理器
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(Handler::class, 'handler_id');
    }

    /**
     * 获取渠道的交易记录
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WithdrawTransaction::class, 'channel_id');
    }

    /**
     * 获取渠道的绑定记录
     */
    public function bindings(): HasMany
    {
        return $this->hasMany(WithdrawBinding::class, 'channel_id');
    }

    /**
     * 获取配置值
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    /**
     * 设置配置值
     */
    public function setConfig(string $key, mixed $value): self
    {
        $config = $this->config ?? [];
        data_set($config, $key, $value);
        $this->config = $config;
        
        return $this;
    }

    /**
     * 激活渠道
     */
    public function activate(): self
    {
        $this->is_active = true;
        $this->save();
        
        return $this;
    }

    /**
     * 停用渠道
     */
    public function deactivate(): self
    {
        $this->is_active = false;
        $this->save();
        
        return $this;
    }

    /**
     * 作用域：激活的渠道
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * 作用域：按排序
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort')->orderBy('id');
    }
}
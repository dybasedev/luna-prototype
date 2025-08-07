<?php

namespace Dybasedev\LunaPrototype\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler;
use Dybasedev\LunaPrototype\Foundation\Handler\WithModelHandler;
use Dybasedev\LunaPrototype\Content\LunaContentConfigure;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContentChannel extends Model
{
    use WithModelHandler;

    /**
     * 表名
     *
     * @var string
     */
    protected $table = 'luna_content_channels';

    /**
     * 不自增主键
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * 可填充字段
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'name',
        'display_name',
        'description',
        'handler_id',
        'config',
        'is_active',
        'sort',
    ];

    /**
     * 类型转换
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'handler_id' => 'integer',
        'config' => 'array',
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];

    /**
     * 默认属性值
     *
     * @var array
     */
    protected $attributes = [
        'is_active' => true,
        'sort' => 0,
        'config' => '[]',
        'description' => '',
    ];

    /**
     * 获取处理器
     *
     * @return BelongsTo
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(Handler::class, 'handler_id');
    }

    /**
     * 获取频道的内容
     *
     * @return BelongsToMany
     */
    public function contents(): BelongsToMany
    {
        return $this->belongsToMany(
            luna_module_configure(LunaContentConfigure::class)->contentModel,
            'luna_channel_contents',
            'channel_id',
            'content_id'
        )
            ->withPivot('sort', 'config')
            ->withTimestamps()
            ->orderByPivot('sort');
    }

    /**
     * 获取已发布的内容
     *
     * @return BelongsToMany
     */
    public function publishedContents(): BelongsToMany
    {
        return $this->contents()->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * 根据名称创建或获取频道
     *
     * @param string $name
     * @param array $attributes
     * @return static
     */
    public static function findOrCreateByName(string $name, array $attributes = []): static
    {
        $id = hash_code($name);
        
        return static::firstOrCreate(
            ['id' => $id],
            array_merge($attributes, [
                'name' => $name,
                'id' => $id,
            ])
        );
    }

    /**
     * 根据名称查找频道
     *
     * @param string $name
     * @return static|null
     */
    public static function findByName(string $name): ?static
    {
        return static::find(hash_code($name));
    }

    /**
     * 启用状态查询作用域
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * 排序查询作用域
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $direction
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrdered($query, string $direction = 'asc')
    {
        return $query->orderBy('sort', $direction)->orderBy('id', $direction);
    }
}
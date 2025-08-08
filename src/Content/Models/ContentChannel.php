<?php

namespace Dybasedev\LunaPrototype\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler;
use Dybasedev\LunaPrototype\Foundation\Handler\WithModelHandler;
use Dybasedev\LunaPrototype\Foundation\NamedId;
use Dybasedev\LunaPrototype\Content\LunaContentConfigure;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 内容频道模型
 *
 * @property int $id 频道ID（name的hash code）
 * @property string $name 频道唯一标识符
 * @property string $display_name 频道显示名称
 * @property string|null $description 频道描述
 * @property int|null $handler_id 处理器ID
 * @property array $config 频道配置
 * @property bool $is_active 是否激活
 * @property int $sort 排序值
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Handler|null $handler
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Content> $contents
 * @property-read int|null $contents_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Content> $publishedContents
 * @property-read int|null $published_contents_count
 * @method static \Illuminate\Database\Eloquent\Builder|ContentChannel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ContentChannel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ContentChannel query()
 * @method static \Illuminate\Database\Eloquent\Builder|ContentChannel active()
 * @method static \Illuminate\Database\Eloquent\Builder|ContentChannel ordered(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder|ContentChannel whereConfig($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentChannel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentChannel whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentChannel whereDisplayName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentChannel whereHandlerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentChannel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentChannel whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentChannel whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentChannel whereSort($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ContentChannel whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ContentChannel extends Model
{
    use NamedId, WithModelHandler;

    /**
     * 表名
     *
     * @var string
     */
    protected $table = 'luna_content_channels';

    /**
     * 可填充字段
     *
     * @var array
     */
    protected $fillable = [
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
        return static::firstOrCreate(
            ['name' => $name],
            array_merge($attributes, [
                'name' => $name,
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
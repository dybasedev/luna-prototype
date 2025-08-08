<?php

namespace Dybasedev\LunaPrototype\Content\Models;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler;
use Dybasedev\LunaPrototype\Foundation\Handler\WithModelHandler;
use Dybasedev\LunaPrototype\Content\LunaContentConfigure;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * 内容模型
 *
 * @property int $id
 * @property int|null $owner_type 所有者类型
 * @property int|null $owner_id 所有者ID
 * @property string $name 内容唯一标识符
 * @property string $title 标题
 * @property string|null $keywords 关键词
 * @property string|null $description 描述
 * @property int|null $handler_id 处理器ID
 * @property array|null $handler_config 处理器配置
 * @property string|null $current_version_id 当前版本ID
 * @property array $payload 载荷数据
 * @property \Illuminate\Support\Carbon|null $published_at 发布时间
 * @property int $views_count 浏览次数
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string|null $content 内容正文（从当前版本获取）
 * @property-read Model|\Eloquent|null $owner
 * @property-read Handler|null $handler
 * @property-read ContentVersion|null $currentVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContentVersion> $versions
 * @property-read int|null $versions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContentChannel> $channels
 * @property-read int|null $channels_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContentCategory> $categories
 * @property-read int|null $categories_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContentMetadata> $metadata
 * @property-read int|null $metadata_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContentAttachment> $attachments
 * @property-read int|null $attachments_count
 * @method static \Illuminate\Database\Eloquent\Builder|Content newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Content newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Content query()
 * @method static \Illuminate\Database\Eloquent\Builder|Content published()
 * @method static \Illuminate\Database\Eloquent\Builder|Content unpublished()
 * @method static \Illuminate\Database\Eloquent\Builder|Content whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Content whereCurrentVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Content whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Content whereHandlerConfig($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Content whereHandlerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Content whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Content whereKeywords($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Content whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Content whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Content whereOwnerType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Content wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Content wherePublishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Content whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Content whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Content whereViewsCount($value)
 * @mixin \Eloquent
 */
class Content extends Model
{
    use WithModelHandler;

    /**
     * 表名
     *
     * @var string
     */
    protected $table = 'luna_contents';

    /**
     * 可填充字段
     *
     * @var array
     */
    protected $fillable = [
        'owner_type',
        'owner_id',
        'name',
        'title',
        'keywords',
        'description',
        'handler_id',
        'handler_config',
        'current_version_id',
        'published_at',
        'views_count',
    ];

    /**
     * 类型转换
     *
     * @var array
     */
    protected $casts = [
        'owner_type' => 'integer',
        'owner_id' => 'integer',
        'handler_id' => 'integer',
        'handler_config' => 'array',
        'published_at' => 'datetime',
        'views_count' => 'integer',
    ];

    /**
     * 默认属性值
     *
     * @var array
     */
    protected $attributes = [
        'views_count' => 0,
    ];

    /**
     * 获取所有者
     *
     * @return MorphTo
     */
    public function owner(): MorphTo
    {
        return $this->morphTo('owner', 'owner_type', 'owner_id');
    }

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
     * 获取当前版本
     *
     * @return HasOne
     * @throws BindingResolutionException
     */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(
            luna_module_configure(LunaContentConfigure::class)->versionModel,
            'version_id',
            'current_version_id'
        );
    }

    /**
     * 获取所有版本
     *
     * @return HasMany
     * @throws BindingResolutionException
     */
    public function versions(): HasMany
    {
        return $this->hasMany(
            luna_module_configure(LunaContentConfigure::class)->versionModel,
            'content_id'
        )->orderBy('created_at', 'desc');
    }

    /**
     * 获取频道
     *
     * @return BelongsToMany
     * @throws BindingResolutionException
     */
    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(
            luna_module_configure(LunaContentConfigure::class)->channelModel,
            'luna_channel_contents',
            'content_id',
            'channel_id'
        )
            ->withPivot('sort', 'config')
            ->withTimestamps();
    }

    /**
     * 获取分类
     *
     * @return BelongsToMany
     * @throws BindingResolutionException
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            luna_module_configure(LunaContentConfigure::class)->categoryModel,
            'luna_content_category_relations',
            'content_id',
            'category_id'
        )
            ->withPivot('sort')
            ->withTimestamps()
            ->orderByPivot('sort');
    }

    /**
     * 获取元数据
     *
     * @return HasMany
     * @throws BindingResolutionException
     */
    public function metadata(): HasMany
    {
        return $this->hasMany(
            luna_module_configure(LunaContentConfigure::class)->metadataModel,
            'content_id'
        );
    }

    /**
     * 获取附件
     *
     * @return HasMany
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(
            luna_module_configure(LunaContentConfigure::class)->attachmentModel,
            'owner_id'
        )->where('owner_type', hash_code(static::class));
    }


    /**
     * 创建新版本
     *
     * @param string $content
     * @param array $attributes
     * @param SessionHolder|null $editor
     * @return ContentVersion
     */
    public function createVersion(string $content, array $attributes = [], ?SessionHolder $editor = null): ContentVersion
    {
        $versionId = Str::uuid()->toString();
        
        // 如果没有提供 payload，使用当前内容的 payload
        if (!isset($attributes['payload'])) {
            $attributes['payload'] = $this->payload;
        }
        
        $versionData = array_merge($attributes, [
            'version_id' => $versionId,
            'content_id' => $this->id,
            'content' => $content,
            'editor_type' => $editor ? hash_code(get_class($editor)) : null,
            'editor_id' => $editor ? $editor->getOperatorId() : null,
        ]);

        $versionModel = luna_module_configure(LunaContentConfigure::class)->versionModel;
        $version = $versionModel::create($versionData);

        // 如果没有当前版本，设置为当前版本
        if (!$this->current_version_id) {
            $this->update(['current_version_id' => $versionId]);
        }

        return $version;
    }

    /**
     * 应用指定版本
     *
     * @param string $versionId
     * @return bool
     * @throws BindingResolutionException
     */
    public function applyVersion(string $versionId): bool
    {
        $version = $this->versions()->where('version_id', $versionId)->first();
        
        if (!$version) {
            return false;
        }

        // 更新当前版本ID
        return $this->update(['current_version_id' => $versionId]);
    }

    /**
     * 获取内容（从当前版本）
     *
     * @return string|null
     */
    public function getContentAttribute(): ?string
    {
        return $this->currentVersion?->content;
    }
    
    /**
     * 获取 payload（从当前版本）
     *
     * @return array
     */
    public function getPayloadAttribute(): array
    {
        // payload 应该始终从版本获取，就像 content 一样
        return $this->currentVersion?->payload ?? [];
    }

    /**
     * 获取指定的元数据值
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getMetadata(string $key, $default = null)
    {
        $metadata = $this->metadata()->where('key', $key)->first();
        
        if (!$metadata) {
            return $default;
        }

        return $metadata->getTypedValue();
    }

    /**
     * 设置元数据
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $type
     * @return ContentMetadata
     * @throws BindingResolutionException
     */
    public function setMetadata(string $key, $value, ?int $type = null): ContentMetadata
    {
        $metadata = $this->metadata()->where('key', $key)->first();
        
        if ($metadata) {
            $metadata->setTypedValue($value);
            $metadata->save();
            return $metadata;
        }
        
        return ContentMetadata::createFor($this->id, $key, $value, $type);
    }

    /**
     * 增加浏览次数
     *
     * @param int $count
     * @return bool
     */
    public function incrementViews(int $count = 1): bool
    {
        return $this->increment('views_count', $count);
    }

    /**
     * 判断是否已发布
     *
     * @return bool
     */
    public function isPublished(): bool
    {
        return $this->published_at && $this->published_at->lte(now());
    }

    /**
     * 发布内容
     *
     * @param \DateTimeInterface|null $publishAt
     * @return bool
     */
    public function publish(?\DateTimeInterface $publishAt = null): bool
    {
        return $this->update(['published_at' => $publishAt ?: now()]);
    }

    /**
     * 取消发布
     *
     * @return bool
     */
    public function unpublish(): bool
    {
        return $this->update(['published_at' => null]);
    }

    /**
     * 已发布查询作用域
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * 未发布查询作用域
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnpublished($query)
    {
        return $query->where(function ($query) {
            $query->whereNull('published_at')
                ->orWhere('published_at', '>', now());
        });
    }

    /**
     * 根据名称查找内容
     *
     * @param string $name
     * @return static|null
     */
    public static function findByName(string $name): ?static
    {
        return static::where('name', $name)->first();
    }

    /**
     * 附加到频道
     *
     * @param int|ContentChannel $channel
     * @param array $pivotData
     * @return void
     */
    public function attachToChannel($channel, array $pivotData = []): void
    {
        $channelId = $channel instanceof ContentChannel ? $channel->id : $channel;
        
        $this->channels()->attach($channelId, $pivotData);
    }

    /**
     * 从频道移除
     *
     * @param int|ContentChannel $channel
     * @return void
     */
    public function detachFromChannel($channel): void
    {
        $channelId = $channel instanceof ContentChannel ? $channel->id : $channel;
        
        $this->channels()->detach($channelId);
    }

    /**
     * 附加到分类
     *
     * @param int|ContentCategory $category
     * @param array $pivotData
     * @return void
     */
    public function attachToCategory($category, array $pivotData = []): void
    {
        $categoryId = $category instanceof ContentCategory ? $category->id : $category;
        
        $this->categories()->attach($categoryId, $pivotData);
    }

    /**
     * 从分类移除
     *
     * @param int|ContentCategory $category
     * @return void
     */
    public function detachFromCategory($category): void
    {
        $categoryId = $category instanceof ContentCategory ? $category->id : $category;
        
        $this->categories()->detach($categoryId);
    }
}
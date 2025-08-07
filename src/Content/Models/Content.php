<?php

namespace Dybasedev\LunaPrototype\Content\Models;

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
        'payload',
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
        'payload' => 'array',
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
        'payload' => '[]',
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
     */
    public function applyVersion(string $versionId): bool
    {
        $version = $this->versions()->where('version_id', $versionId)->first();
        
        if (!$version) {
            return false;
        }

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
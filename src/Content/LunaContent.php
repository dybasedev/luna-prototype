<?php

namespace Dybasedev\LunaPrototype\Content;

use Dybasedev\LunaPrototype\Foundation\LunaModule;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Content\Models\Content;
use Dybasedev\LunaPrototype\Content\Models\ContentChannel;
use Dybasedev\LunaPrototype\Content\Models\ContentCategory;
use Dybasedev\LunaPrototype\Content\Models\ContentVersion;
use Dybasedev\LunaPrototype\Content\Models\ContentAttachment;
use Dybasedev\LunaPrototype\Content\Handlers\BaseContentHandler;
use Dybasedev\LunaPrototype\Content\Handlers\BaseChannelHandler;
use Dybasedev\LunaPrototype\Content\Handlers\DefaultContentHandler;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Dybasedev\LunaPrototype\Content\Builders\ContentBuilder;
use Dybasedev\LunaPrototype\Content\Builders\ContentUpdateBuilder;
use Dybasedev\LunaPrototype\Content\Builders\ChannelBuilder;

class LunaContent extends LunaModule
{
    protected(set) LunaContentConfigure $configure;
    protected(set) LunaHandler $handler;
    protected(set) CacheRepository $cache;
    
    /**
     * 构造函数
     *
     * @param LunaContentConfigure $configure
     * @param LunaHandler $handler
     * @param CacheRepository $cache
     */
    public function __construct(
        LunaContentConfigure $configure,
        LunaHandler $handler,
        CacheRepository $cache
    ) {
        $this->configure = $configure;
        $this->handler = $handler;
        $this->cache = $cache;
    }

    /**
     * 创建内容
     *
     * @param array<string, mixed> $data
     * @param SessionHolder|null $owner
     * @return Content
     */
    public function createContent(array $data, ?SessionHolder $owner = null): Content
    {
        return DB::transaction(function () use ($data, $owner) {
            $contentData = array_merge($data, [
                'owner_type' => $owner ? hash_code(get_class($owner)) : null,
                'owner_id' => $owner ? $owner->getOperatorId() : null,
            ]);

            // 创建内容
            $content = $this->configure->contentModel::create($contentData);

            // 如果启用版本控制且提供了内容，创建初始版本
            if ($this->configure->enableVersioning && isset($data['content'])) {
                $content->createVersion($data['content'], [
                    'version_name' => $data['version_name'] ?? '初始版本',
                    'version_note' => $data['version_note'] ?? null,
                ], $owner);
            }

            // 处理分类
            if ($this->configure->enableCategories && isset($data['categories'])) {
                $content->categories()->attach($data['categories']);
            }

            // 处理频道
            if (isset($data['channels'])) {
                $content->channels()->attach($data['channels']);
            }

            return $content;
        });
    }

    /**
     * 更新内容
     *
     * @param int|Content $content
     * @param array<string, mixed> $data
     * @param SessionHolder|null $editor
     * @return Content
     */
    public function updateContent(int|Content $content, array $data, ?SessionHolder $editor = null): Content
    {
        if (!$content instanceof Content) {
            $content = $this->configure->contentModel::findOrFail($content);
        }

        return DB::transaction(function () use ($content, $data, $editor) {
            // 更新基本信息
            $content->update(\Illuminate\Support\Arr::except($data, ['content', 'categories', 'channels']));

            // 如果启用版本控制且提供了新内容，创建新版本
            if ($this->configure->enableVersioning && isset($data['content'])) {
                $version = $content->createVersion($data['content'], [
                    'version_name' => $data['version_name'] ?? null,
                    'version_note' => $data['version_note'] ?? null,
                ], $editor);

                // 自动应用新版本
                $content->applyVersion($version->version_id);
            }

            // 更新分类
            if ($this->configure->enableCategories && isset($data['categories'])) {
                $content->categories()->sync($data['categories']);
            }

            // 更新频道
            if (isset($data['channels'])) {
                $content->channels()->sync($data['channels']);
            }

            return $content->fresh();
        });
    }

    /**
     * 删除内容
     *
     * @param int|Content $content
     * @return bool
     */
    public function deleteContent(int|Content $content): bool
    {
        if (!$content instanceof Content) {
            $content = $this->configure->contentModel::findOrFail($content);
        }

        return DB::transaction(function () use ($content) {
            // 删除关联数据
            $content->categories()->detach();
            $content->channels()->detach();
            $content->metadata()->delete();
            
            if ($this->configure->enableVersioning) {
                $content->versions()->delete();
            }

            if ($this->configure->enableAttachments) {
                // 删除附件文件
                foreach ($content->attachments as $attachment) {
                    $attachment->deleteFile();
                }
            }

            return $content->delete();
        });
    }

    /**
     * 创建或更新频道
     *
     * @param string $name
     * @param array<string, mixed> $attributes
     * @return ContentChannel
     */
    public function createOrUpdateChannel(string $name, array $attributes): ContentChannel
    {
        return $this->configure->channelModel::updateOrCreate(
            ['name' => $name],
            array_merge($attributes, [
                'name' => $name,
            ])
        );
    }

    /**
     * 创建分类
     *
     * @param array<string, mixed> $data
     * @return ContentCategory
     */
    public function createCategory(array $data): ContentCategory
    {
        return $this->configure->categoryModel::create($data);
    }

    /**
     * 获取分类树
     *
     * @param int $parentId
     * @param bool $activeOnly
     * @return \Illuminate\Support\Collection<int, ContentCategory>
     */
    public function getCategoryTree(int $parentId = 0, bool $activeOnly = true): \Illuminate\Support\Collection
    {
        $query = $this->configure->categoryModel::query();
        
        if ($activeOnly) {
            $query->active();
        }
        
        $categories = $query->ordered()->get();
        
        return $this->buildCategoryTree($categories, $parentId);
    }

    /**
     * 构建分类树
     *
     * @param \Illuminate\Support\Collection<int, ContentCategory> $categories
     * @param int $parentId
     * @return \Illuminate\Support\Collection<int, ContentCategory>
     */
    protected function buildCategoryTree(\Illuminate\Support\Collection $categories, int $parentId = 0): \Illuminate\Support\Collection
    {
        $branch = collect();

        foreach ($categories->where('parent_id', $parentId) as $category) {
            $children = $this->buildCategoryTree($categories, $category->id);
            if ($children->isNotEmpty()) {
                $category->setRelation('children', $children);
            }
            $branch->push($category);
        }

        return $branch;
    }

    /**
     * 上传附件
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param array<string, mixed> $attributes
     * @param SessionHolder|null $owner
     * @return ContentAttachment
     */
    public function uploadAttachment(\Illuminate\Http\UploadedFile $file, array $attributes = [], ?SessionHolder $owner = null): ContentAttachment
    {
        if (!$this->configure->enableAttachments) {
            throw new \RuntimeException('附件功能未启用');
        }

        $attachmentData = array_merge($attributes, [
            'owner_type' => $owner ? hash_code(get_class($owner)) : null,
            'owner_id' => $owner ? $owner->getOperatorId() : null,
        ]);

        return $this->configure->attachmentModel::createFromUploadedFile(
            $file,
            $attachmentData,
            $attributes['disk'] ?? 'public',
            $attributes['directory'] ?? 'content-attachments'
        );
    }

    /**
     * 从URL创建附件
     *
     * @param string $url
     * @param array<string, mixed> $attributes
     * @param SessionHolder|null $owner
     * @return ContentAttachment
     */
    public function createAttachmentFromUrl(string $url, array $attributes = [], ?SessionHolder $owner = null): ContentAttachment
    {
        if (!$this->configure->enableAttachments) {
            throw new \RuntimeException('附件功能未启用');
        }

        $attachmentData = array_merge($attributes, [
            'owner_type' => $owner ? hash_code(get_class($owner)) : null,
            'owner_id' => $owner ? $owner->getOperatorId() : null,
        ]);

        return $this->configure->attachmentModel::createFromUrl($url, $attachmentData);
    }

    /**
     * 获取内容查询构建器
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function contents(): \Illuminate\Database\Eloquent\Builder
    {
        return $this->configure->contentModel::query();
    }

    /**
     * 获取频道查询构建器
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function channels(): \Illuminate\Database\Eloquent\Builder
    {
        return $this->configure->channelModel::query();
    }

    /**
     * 获取分类查询构建器
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function categories(): \Illuminate\Database\Eloquent\Builder
    {
        return $this->configure->categoryModel::query();
    }

    /**
     * 获取附件查询构建器
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function attachments(): \Illuminate\Database\Eloquent\Builder
    {
        if (!$this->configure->enableAttachments) {
            throw new \RuntimeException('附件功能未启用');
        }

        return $this->configure->attachmentModel::query();
    }


    /**
     * 获取配置
     *
     * @return LunaContentConfigure
     */
    public function getConfig(): LunaContentConfigure
    {
        return $this->configure;
    }
    
    /**
     * 创建内容构建器
     *
     * @return ContentBuilder
     */
    public function newContent(): ContentBuilder
    {
        return ContentBuilder::create();
    }
    
    /**
     * 创建内容更新构建器
     *
     * @param Content|int $content
     * @return ContentUpdateBuilder
     */
    public function editContent(Content|int $content): ContentUpdateBuilder
    {
        return ContentUpdateBuilder::for($content);
    }
    
    /**
     * 创建频道构建器
     *
     * @return ChannelBuilder
     */
    public function newChannel(): ChannelBuilder
    {
        return ChannelBuilder::create();
    }

    /**
     * 发布内容到频道
     *
     * @param Content|int $content
     * @param ContentChannel|string $channel
     * @param array<string, mixed> $pivotData
     * @param SessionHolder|null $publisher
     * @return bool
     */
    public function publishToChannel(Content|int $content, ContentChannel|string $channel, array $pivotData = [], ?SessionHolder $publisher = null): bool
    {
        if (!$content instanceof Content) {
            $content = $this->configure->contentModel::findOrFail($content);
        }

        if (!$channel instanceof ContentChannel) {
            $channel = $this->configure->channelModel::findByName($channel);
            if (!$channel) {
                throw new \InvalidArgumentException("频道 {$channel} 不存在");
            }
        }

        // 获取频道处理器
        $handler = $this->handler->createHandlerInstance($channel->handler_id);
        
        if (!$handler instanceof BaseChannelHandler) {
            throw new \RuntimeException('无效的频道处理器');
        }

        // 检查是否可以发布
        if (!$handler->canPublish($content, $channel, $publisher)) {
            return false;
        }

        return DB::transaction(function () use ($content, $channel, $pivotData, $handler) {
            // 发布前处理
            $pivotData = $handler->beforePublishToChannel($content, $channel, $pivotData);

            // 附加到频道
            $content->attachToChannel($channel, $pivotData);

            // 发布后处理
            $handler->afterPublishToChannel($content, $channel);

            return true;
        });
    }

    /**
     * 从频道移除内容
     *
     * @param Content|int $content
     * @param ContentChannel|string $channel
     * @return bool
     */
    public function removeFromChannel(Content|int $content, ContentChannel|string $channel): bool
    {
        if (!$content instanceof Content) {
            $content = $this->configure->contentModel::findOrFail($content);
        }

        if (!$channel instanceof ContentChannel) {
            $channel = $this->configure->channelModel::findByName($channel);
            if (!$channel) {
                return false;
            }
        }

        // 获取频道处理器
        $handler = $this->handler->createHandlerInstance($channel->handler_id);
        
        if (!$handler instanceof BaseChannelHandler) {
            throw new \RuntimeException('无效的频道处理器');
        }

        // 检查是否可以移除
        if (!$handler->beforeRemoveFromChannel($content, $channel)) {
            return false;
        }

        return DB::transaction(function () use ($content, $channel, $handler) {
            // 从频道移除
            $content->detachFromChannel($channel);

            // 移除后处理
            $handler->afterRemoveFromChannel($content, $channel);

            return true;
        });
    }


    /**
     * 渲染内容
     *
     * @param Content|int $content
     * @param array<string, mixed> $options
     * @return \Dybasedev\LunaPrototype\Content\Results\ContentResult
     */
    public function renderContent(Content|int $content, array $options = []): \Dybasedev\LunaPrototype\Content\Results\ContentResult
    {
        if (!$content instanceof Content) {
            $content = $this->configure->contentModel::findOrFail($content);
        }

        if (!$content->handler_id) {
            // 如果没有处理器，使用默认处理器
            $handler = $this->handler->getPureHandler(DefaultContentHandler::class);
        } else {
            $handler = $this->handler->createHandlerInstance($content->handler_id);
        }
        
        if (!$handler instanceof BaseContentHandler) {
            throw new \RuntimeException('无效的内容处理器');
        }

        return $handler->render($content, $options);
    }

    /**
     * 批量渲染内容
     *
     * @param \Illuminate\Support\Collection|array<int, Content> $contents
     * @param array<string, mixed> $options
     * @return \Illuminate\Support\Collection
     */
    public function batchRenderContents(\Illuminate\Support\Collection|array $contents, array $options = []): \Illuminate\Support\Collection
    {
        $contents = collect($contents);
        $grouped = $contents->groupBy('handler_id');
        $results = collect();

        foreach ($grouped as $handlerId => $group) {
            if (!$handlerId) {
                // 没有处理器的内容
                foreach ($group as $content) {
                    $results->push($content->toArray());
                }
                continue;
            }

            try {
                $handler = $this->handler->createHandlerInstance($handlerId);
                if ($handler instanceof BaseContentHandler) {
                    $rendered = $handler->batchProcess($group, $options);
                    foreach ($rendered as $item) {
                        $results->push($item);
                    }
                }
            } catch (\Exception $e) {
                // 如果处理器不存在或创建失败，跳过该组
                continue;
            }
        }

        return $results;
    }

    /**
     * 验证内容数据
     *
     * @param array<string, mixed> $data
     * @param Content|null $content
     * @return \Illuminate\Contracts\Validation\Validator
     */
    public function validateContent(array $data, ?Content $content = null): \Illuminate\Contracts\Validation\Validator
    {
        $rules = [
            'name' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'keywords' => 'nullable|string|max:1000',
        ];

        // 如果是新建内容，name必须唯一
        if (!$content) {
            $rules['name'] .= '|unique:luna_contents,name';
        } else {
            $rules['name'] .= '|unique:luna_contents,name,' . $content->id;
        }

        // 如果指定了处理器，使用处理器的验证规则
        if (isset($data['handler_id'])) {
            try {
                $handler = $this->handler->createHandlerInstance($data['handler_id']);
                if ($handler instanceof BaseContentHandler) {
                    $rules = array_merge($rules, $handler->validationRules());
                }
            } catch (\Exception $e) {
                // 如果处理器不存在或创建失败，使用默认规则
            }
        }

        return Validator::make($data, $rules);
    }

    /**
     * 搜索内容
     *
     * @param string $keyword
     * @param array<string, mixed> $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function searchContents(string $keyword, array $filters = []): \Illuminate\Database\Eloquent\Builder
    {
        $query = $this->contents();

        // 关键词搜索
        $query->where(function ($q) use ($keyword) {
            $q->where('title', 'like', "%{$keyword}%")
              ->orWhere('description', 'like', "%{$keyword}%")
              ->orWhere('keywords', 'like', "%{$keyword}%");
        });

        // 应用过滤器
        if (isset($filters['channel_id'])) {
            $query->whereHas('channels', function ($q) use ($filters) {
                $q->where('channel_id', $filters['channel_id']);
            });
        }

        if (isset($filters['category_id'])) {
            $query->whereHas('categories', function ($q) use ($filters) {
                $q->where('category_id', $filters['category_id']);
            });
        }

        if (isset($filters['published']) && $filters['published']) {
            $query->published();
        }

        if (isset($filters['owner_type']) && isset($filters['owner_id'])) {
            $query->where('owner_type', $filters['owner_type'])
                  ->where('owner_id', $filters['owner_id']);
        }

        return $query;
    }

    /**
     * 获取内容统计信息
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getContentStatistics(array $filters = []): array
    {
        $query = $this->contents();

        // 应用过滤器
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return [
            'total' => $query->count(),
            'published' => (clone $query)->published()->count(),
            'unpublished' => (clone $query)->unpublished()->count(),
            'total_views' => $query->sum('views_count'),
            'with_attachments' => (clone $query)->has('attachments')->count(),
            'by_channel' => $this->getChannelStatistics($filters),
            'by_category' => $this->getCategoryStatistics($filters),
        ];
    }

    /**
     * 获取频道统计
     *
     * @param array<string, mixed> $filters
     * @return array<string, array<string, int>>
     */
    protected function getChannelStatistics(array $filters = []): array
    {
        $stats = [];
        $channels = $this->channels()->active()->get();

        foreach ($channels as $channel) {
            $query = $channel->contents();
            
            if (isset($filters['date_from'])) {
                $query->where('created_at', '>=', $filters['date_from']);
            }

            if (isset($filters['date_to'])) {
                $query->where('created_at', '<=', $filters['date_to']);
            }

            $stats[$channel->name] = [
                'total' => $query->count(),
                'published' => (clone $query)->published()->count(),
            ];
        }

        return $stats;
    }

    /**
     * 获取分类统计
     *
     * @param array<string, mixed> $filters
     * @return array<string, array<string, int>>
     */
    protected function getCategoryStatistics(array $filters = []): array
    {
        $stats = [];
        $categories = $this->categories()->active()->get();

        foreach ($categories as $category) {
            $query = $category->contents();
            
            if (isset($filters['date_from'])) {
                $query->where('created_at', '>=', $filters['date_from']);
            }

            if (isset($filters['date_to'])) {
                $query->where('created_at', '<=', $filters['date_to']);
            }

            $stats[$category->name] = [
                'total' => $query->count(),
                'published' => (clone $query)->published()->count(),
            ];
        }

        return $stats;
    }
}
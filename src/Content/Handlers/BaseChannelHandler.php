<?php

namespace Dybasedev\LunaPrototype\Content\Handlers;

use Dybasedev\LunaPrototype\Foundation\Handler\BaseHandler;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Content\Models\Content;
use Dybasedev\LunaPrototype\Content\Models\ContentChannel;

abstract class BaseChannelHandler extends BaseHandler
{
    /**
     * 频道处理器不需要实体
     * 
     * @return bool
     */
    public static function requiresEntity(): bool
    {
        return false;
    }
    
    /**
     * 内容发布到频道前的检查
     *
     * @param Content $content
     * @param ContentChannel $channel
     * @param SessionHolder|null $publisher
     * @return bool
     */
    abstract public function canPublish(Content $content, ContentChannel $channel, ?SessionHolder $publisher = null): bool;

    /**
     * 内容发布到频道前的处理
     *
     * @param Content $content
     * @param ContentChannel $channel
     * @param array $pivotData
     * @return array 返回处理后的关联数据
     */
    public function beforePublishToChannel(Content $content, ContentChannel $channel, array $pivotData = []): array
    {
        return $pivotData;
    }

    /**
     * 内容发布到频道后的处理
     *
     * @param Content $content
     * @param ContentChannel $channel
     * @return void
     */
    public function afterPublishToChannel(Content $content, ContentChannel $channel): void
    {
        // 子类可以重写此方法，例如发送通知、记录日志等
    }

    /**
     * 内容从频道移除前的处理
     *
     * @param Content $content
     * @param ContentChannel $channel
     * @return bool
     */
    public function beforeRemoveFromChannel(Content $content, ContentChannel $channel): bool
    {
        return true;
    }

    /**
     * 内容从频道移除后的处理
     *
     * @param Content $content
     * @param ContentChannel $channel
     * @return void
     */
    public function afterRemoveFromChannel(Content $content, ContentChannel $channel): void
    {
        // 子类可以重写此方法
    }

    /**
     * 获取频道的内容列表
     *
     * @param ContentChannel $channel
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getContents(ContentChannel $channel, array $filters = [])
    {
        $query = $channel->contents();

        // 应用过滤器
        if (isset($filters['published'])) {
            $query->published();
        }

        if (isset($filters['category_id'])) {
            $query->whereHas('categories', function ($q) use ($filters) {
                $q->where('category_id', $filters['category_id']);
            });
        }

        if (isset($filters['owner_type']) && isset($filters['owner_id'])) {
            $query->where('owner_type', $filters['owner_type'])
                  ->where('owner_id', $filters['owner_id']);
        }

        return $query;
    }

    /**
     * 频道内容排序
     *
     * @param ContentChannel $channel
     * @param array $contentIds
     * @return void
     */
    public function sortContents(ContentChannel $channel, array $contentIds): void
    {
        foreach ($contentIds as $sort => $contentId) {
            $channel->contents()->updateExistingPivot($contentId, ['sort' => $sort]);
        }
    }

    /**
     * 验证频道配置
     *
     * @param array $config
     * @return bool
     */
    public function validateConfig(array $config): bool
    {
        return true;
    }

    /**
     * 获取频道的默认配置
     *
     * @return array
     */
    public function defaultConfig(): array
    {
        return [
            'auto_publish' => false,
            'require_review' => false,
            'max_contents' => null,
        ];
    }

    /**
     * 检查频道是否已满
     *
     * @param ContentChannel $channel
     * @return bool
     */
    public function isChannelFull(ContentChannel $channel): bool
    {
        $maxContents = $channel->config['max_contents'] ?? null;
        
        if ($maxContents === null) {
            return false;
        }

        return $channel->contents()->count() >= $maxContents;
    }

    /**
     * 获取频道统计信息
     *
     * @param ContentChannel $channel
     * @return array
     */
    public function getStatistics(ContentChannel $channel): array
    {
        return [
            'total_contents' => $channel->contents()->count(),
            'published_contents' => $channel->publishedContents()->count(),
            'unpublished_contents' => $channel->contents()->unpublished()->count(),
            'total_views' => (int) $channel->contents()->sum('views_count'),
        ];
    }

    /**
     * 批量发布内容到频道
     *
     * @param array $contentIds
     * @param ContentChannel $channel
     * @param SessionHolder|null $publisher
     * @return array
     */
    public function batchPublish(array $contentIds, ContentChannel $channel, ?SessionHolder $publisher = null): array
    {
        $results = [
            'success' => [],
            'failed' => [],
        ];

        foreach ($contentIds as $contentId) {
            $content = Content::find($contentId);
            
            if (!$content) {
                $results['failed'][$contentId] = '内容不存在';
                continue;
            }

            if (!$this->canPublish($content, $channel, $publisher)) {
                $results['failed'][$contentId] = '无权发布';
                continue;
            }

            try {
                $content->attachToChannel($channel);
                $results['success'][] = $contentId;
            } catch (\Exception $e) {
                $results['failed'][$contentId] = $e->getMessage();
            }
        }

        return $results;
    }
}
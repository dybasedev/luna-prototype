<?php

namespace Dybasedev\LunaPrototype\Content;

use Dybasedev\LunaPrototype\Foundation\Handler\BaseHandler;
use Dybasedev\LunaPrototype\Content\Models\Content;
use Dybasedev\LunaPrototype\Content\Models\ContentChannel;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;

abstract class ChannelHandler extends BaseHandler
{
    /**
     * 频道实例
     *
     * @var ContentChannel|null
     */
    protected ?ContentChannel $channel = null;

    /**
     * 设置频道
     *
     * @param ContentChannel $channel
     * @return void
     */
    public function setChannel(ContentChannel $channel): void
    {
        $this->channel = $channel;
    }

    /**
     * 获取频道
     *
     * @return ContentChannel|null
     */
    public function getChannel(): ?ContentChannel
    {
        return $this->channel;
    }

    /**
     * 检查是否可以发布内容
     *
     * @param Content $content
     * @param SessionHolder|null $operator
     * @return array
     */
    abstract public function canPublish(Content $content, ?SessionHolder $operator = null): array;

    /**
     * 发布前处理
     *
     * @param Content $content
     * @param array $options
     * @return void
     */
    public function beforePublish(Content $content, array $options = []): void
    {
        // 子类可以重写此方法
    }

    /**
     * 发布后处理
     *
     * @param Content $content
     * @param array $options
     * @return void
     */
    public function afterPublish(Content $content, array $options = []): void
    {
        // 子类可以重写此方法
    }

    /**
     * 取消发布前处理
     *
     * @param Content $content
     * @param array $options
     * @return void
     */
    public function beforeUnpublish(Content $content, array $options = []): void
    {
        // 子类可以重写此方法
    }

    /**
     * 取消发布后处理
     *
     * @param Content $content
     * @param array $options
     * @return void
     */
    public function afterUnpublish(Content $content, array $options = []): void
    {
        // 子类可以重写此方法
    }

    /**
     * 批量发布
     *
     * @param array $contents
     * @param SessionHolder|null $operator
     * @return array
     */
    public function batchPublish(array $contents, ?SessionHolder $operator = null): array
    {
        $results = [
            'success' => [],
            'failed' => [],
        ];

        foreach ($contents as $content) {
            $check = $this->canPublish($content, $operator);
            
            if ($check['success']) {
                $this->beforePublish($content);
                $content->publish();
                $this->afterPublish($content);
                
                $results['success'][] = [
                    'content_id' => $content->id,
                    'content_name' => $content->name,
                ];
            } else {
                $results['failed'][] = [
                    'content_id' => $content->id,
                    'content_name' => $content->name,
                    'reason' => $check['message'],
                ];
            }
        }

        return $results;
    }

    /**
     * 获取频道的统计信息
     *
     * @return array
     */
    public function getStatistics(): array
    {
        return [];
    }

    /**
     * 检查频道容量
     *
     * @return array
     */
    public function checkCapacity(): array
    {
        $channel = $this->getChannel();
        
        if (!$channel) {
            return [
                'has_capacity' => true,
                'current_count' => 0,
                'max_capacity' => 0,
                'remaining' => 0,
            ];
        }

        $maxCapacity = $this->getConfig('max_capacity', 0);
        $currentCount = $channel->contents()->count();
        
        return [
            'has_capacity' => $maxCapacity === 0 || $currentCount < $maxCapacity,
            'current_count' => $currentCount,
            'max_capacity' => $maxCapacity,
            'remaining' => $maxCapacity > 0 ? max(0, $maxCapacity - $currentCount) : -1,
        ];
    }
}
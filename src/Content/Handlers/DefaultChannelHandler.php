<?php

namespace Dybasedev\LunaPrototype\Content\Handlers;

use Dybasedev\LunaPrototype\Content\Handlers\BaseChannelHandler;
use Dybasedev\LunaPrototype\Content\Models\Content;
use Dybasedev\LunaPrototype\Content\Models\ContentChannel;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;

class DefaultChannelHandler extends BaseChannelHandler
{
    /**
     * 获取处理器名称
     *
     * @return string
     */
    public function handlerName(): string
    {
        return '默认频道处理器';
    }

    /**
     * 获取处理器描述
     *
     * @return string
     */
    public function handlerDescription(): string
    {
        return '提供基础的内容发布功能，包括权限检查和发布通知';
    }

    /**
     * 检查是否可以发布内容
     *
     * @param Content $content
     * @param ContentChannel $channel
     * @param SessionHolder|null $publisher
     * @return bool
     */
    public function canPublish(Content $content, ContentChannel $channel, ?SessionHolder $publisher = null): bool
    {
        // 默认处理器允许所有人发布（即使没有当前版本）
        return true;
    }

    /**
     * 发布前处理
     *
     * @param Content $content
     * @param array $options
     * @return void
     */
    public function beforePublish(Content $content, array $options = []): void
    {
        // 记录发布日志
        $this->logPublishEvent($content, 'before_publish', $options);
        
        // 可以在这里进行内容审核、敏感词过滤等
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
        // 记录发布日志
        $this->logPublishEvent($content, 'after_publish', $options);
        
        // 发送通知
        if ($this->getConfig('send_notifications', true)) {
            $this->sendPublishNotification($content);
        }
        
        // 清除缓存
        $this->clearContentCache($content);
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
        // 记录取消发布日志
        $this->logPublishEvent($content, 'before_unpublish', $options);
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
        // 记录取消发布日志
        $this->logPublishEvent($content, 'after_unpublish', $options);
        
        // 清除缓存
        $this->clearContentCache($content);
    }

    /**
     * 记录发布事件
     *
     * @param Content $content
     * @param string $event
     * @param array $context
     * @return void
     */
    protected function logPublishEvent(Content $content, string $event, array $context = []): void
    {
        // 这里可以集成日志系统
        logger()->info("Content {$event}", [
            'content_id' => $content->id,
            'content_name' => $content->name,
            'event' => $event,
            'context' => $context,
        ]);
    }

    /**
     * 发送发布通知
     *
     * @param Content $content
     * @return void
     */
    protected function sendPublishNotification(Content $content): void
    {
        // 这里可以集成通知系统
        // 例如发送邮件、推送通知等
    }

    /**
     * 清除内容缓存
     *
     * @param Content $content
     * @return void
     */
    protected function clearContentCache(Content $content): void
    {
        // 这里可以集成缓存系统
        // 清除相关的缓存键
    }
}
<?php

namespace Dybasedev\LunaPrototype\Content;

use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;
use Dybasedev\LunaPrototype\Foundation\LunaModule;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandlerConfigure;
use Dybasedev\LunaPrototype\Content\Models\Content;
use Dybasedev\LunaPrototype\Content\Models\ContentChannel;
use Dybasedev\LunaPrototype\Content\Models\ContentCategory;
use Dybasedev\LunaPrototype\Content\Models\ContentVersion;
use Dybasedev\LunaPrototype\Content\Models\ContentMetadata;
use Dybasedev\LunaPrototype\Content\Models\ContentAttachment;
use Dybasedev\LunaPrototype\Content\Handlers\ArticleContentHandler;
use Dybasedev\LunaPrototype\Content\Handlers\HtmlContentHandler;
use Dybasedev\LunaPrototype\Content\Handlers\MarkdownContentHandler;
use Dybasedev\LunaPrototype\Content\Handlers\DefaultChannelHandler;
use Illuminate\Contracts\Container\BindingResolutionException;

class LunaContentConfigure extends LunaModuleConfigure
{
    /**
     * 内容模型类
     */
    protected(set) string $contentModel = Content::class;

    /**
     * 频道模型类
     */
    protected(set) string $channelModel = ContentChannel::class;

    /**
     * 分类模型类
     */
    protected(set) string $categoryModel = ContentCategory::class;

    /**
     * 版本模型类
     */
    protected(set) string $versionModel = ContentVersion::class;

    /**
     * 元数据模型类
     */
    protected(set) string $metadataModel = ContentMetadata::class;

    /**
     * 附件模型类
     */
    protected(set) string $attachmentModel = ContentAttachment::class;

    /**
     * 启用版本控制
     */
    protected(set) bool $enableVersioning = true;

    /**
     * 启用分类功能
     */
    protected(set) bool $enableCategories = true;

    /**
     * 启用附件功能
     */
    protected(set) bool $enableAttachments = true;

    /**
     * 默认内容处理器组名称
     */
    protected(set) string $contentHandlerGroup = 'content-handlers';

    /**
     * 默认频道处理器组名称
     */
    protected(set) string $channelHandlerGroup = 'channel-handlers';

    /**
     * 获取模块名称
     *
     * @return string
     */
    public function name(): string
    {
        return 'luna.content';
    }

    /**
     * 获取服务提供者类
     *
     * @return string
     */
    public function serviceProvider(): string
    {
        return LunaContentServiceProvider::class;
    }

    /**
     * 注册服务
     *
     * @param \Illuminate\Contracts\Container\Container $container
     * @return void
     */
    public function register(\Illuminate\Contracts\Container\Container $container): void
    {
        // 注册模块实例
        $container->singleton(LunaContent::class, function ($app) {
            return new LunaContent(
                $this,
                $app->make('luna.handler'),
                $app->make('cache.store')
            );
        });

        // 注册别名
        $container->alias(LunaContent::class, 'luna.content');
    }

    /**
     * 启动服务
     *
     * @param \Illuminate\Contracts\Container\Container $container
     * @return void
     * @throws BindingResolutionException
     */
    public function boot(\Illuminate\Contracts\Container\Container $container): void
    {
        // 使用 LunaHandlerConfigure 注册内容处理器组和默认处理器
        $container->make(LunaHandlerConfigure::class)
            ->group($this->contentHandlerGroup, '内容处理器', function ($register) {
                // 注册默认的内容处理器
                $register->handler(ArticleContentHandler::class);
                $register->handler(HtmlContentHandler::class);
                $register->handler(MarkdownContentHandler::class);
            });

        // 使用 LunaHandlerConfigure 注册频道处理器组和默认处理器
        $container->make(LunaHandlerConfigure::class)
            ->group($this->channelHandlerGroup, '频道处理器', function ($register) {
                // 注册默认的频道处理器
                $register->handler(DefaultChannelHandler::class);
            });
    }

    /**
     * 获取模块实例
     *
     * @return LunaModule
     * @throws BindingResolutionException
     */
    public function module(): LunaModule
    {
        return $this->app->make(LunaContent::class);
    }

    /**
     * 设置内容模型
     *
     * @param string $model
     * @return static
     */
    public function useContentModel(string $model): static
    {
        $this->contentModel = $model;
        return $this;
    }

    /**
     * 设置频道模型
     *
     * @param string $model
     * @return static
     */
    public function useChannelModel(string $model): static
    {
        $this->channelModel = $model;
        return $this;
    }

    /**
     * 设置分类模型
     *
     * @param string $model
     * @return static
     */
    public function useCategoryModel(string $model): static
    {
        $this->categoryModel = $model;
        return $this;
    }

    /**
     * 设置版本模型
     *
     * @param string $model
     * @return static
     */
    public function useVersionModel(string $model): static
    {
        $this->versionModel = $model;
        return $this;
    }

    /**
     * 设置元数据模型
     *
     * @param string $model
     * @return static
     */
    public function useMetadataModel(string $model): static
    {
        $this->metadataModel = $model;
        return $this;
    }

    /**
     * 设置附件模型
     *
     * @param string $model
     * @return static
     */
    public function useAttachmentModel(string $model): static
    {
        $this->attachmentModel = $model;
        return $this;
    }

    /**
     * 禁用版本控制
     *
     * @return static
     */
    public function withoutVersioning(): static
    {
        $this->enableVersioning = false;
        return $this;
    }

    /**
     * 禁用分类功能
     *
     * @return static
     */
    public function withoutCategories(): static
    {
        $this->enableCategories = false;
        return $this;
    }

    /**
     * 禁用附件功能
     *
     * @return static
     */
    public function withoutAttachments(): static
    {
        $this->enableAttachments = false;
        return $this;
    }

    /**
     * 设置内容处理器组名称
     *
     * @param string $group
     * @return static
     */
    public function contentHandlerGroup(string $group): static
    {
        $this->contentHandlerGroup = $group;
        return $this;
    }

    /**
     * 设置频道处理器组名称
     *
     * @param string $group
     * @return static
     */
    public function channelHandlerGroup(string $group): static
    {
        $this->channelHandlerGroup = $group;
        return $this;
    }
}
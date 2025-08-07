<?php

namespace Dybasedev\LunaPrototype\Content\Handlers;

use Dybasedev\LunaPrototype\Foundation\Handler\BaseHandler;
use Dybasedev\LunaPrototype\Content\Models\Content;
use Dybasedev\LunaPrototype\Content\Models\ContentVersion;
use Illuminate\Support\Collection;

abstract class BaseContentHandler extends BaseHandler
{
    /**
     * 内容渲染
     *
     * @param Content $content
     * @param array $options
     * @return array
     */
    abstract public function render(Content $content, array $options = []): array;

    /**
     * 内容验证规则
     *
     * @return array
     */
    abstract public function validationRules(): array;

    /**
     * 内容发布前处理
     *
     * @param Content $content
     * @return void
     */
    public function beforePublish(Content $content): void
    {
        // 子类可以重写此方法
    }

    /**
     * 内容发布后处理
     *
     * @param Content $content
     * @return void
     */
    public function afterPublish(Content $content): void
    {
        // 子类可以重写此方法
    }

    /**
     * 内容创建前处理
     *
     * @param array $data
     * @return array
     */
    public function beforeCreate(array $data): array
    {
        return $data;
    }

    /**
     * 内容创建后处理
     *
     * @param Content $content
     * @return void
     */
    public function afterCreate(Content $content): void
    {
        // 子类可以重写此方法
    }

    /**
     * 内容更新前处理
     *
     * @param Content $content
     * @param array $data
     * @return array
     */
    public function beforeUpdate(Content $content, array $data): array
    {
        return $data;
    }

    /**
     * 内容更新后处理
     *
     * @param Content $content
     * @return void
     */
    public function afterUpdate(Content $content): void
    {
        // 子类可以重写此方法
    }

    /**
     * 内容删除前处理
     *
     * @param Content $content
     * @return bool
     */
    public function beforeDelete(Content $content): bool
    {
        return true;
    }

    /**
     * 内容删除后处理
     *
     * @param Content $content
     * @return void
     */
    public function afterDelete(Content $content): void
    {
        // 子类可以重写此方法
    }

    /**
     * 格式化内容用于显示
     *
     * @param Content $content
     * @param string $format
     * @return mixed
     */
    public function format(Content $content, string $format = 'default')
    {
        $method = 'format' . ucfirst($format);
        
        if (method_exists($this, $method)) {
            return $this->$method($content);
        }

        return $this->formatDefault($content);
    }

    /**
     * 默认格式化
     *
     * @param Content $content
     * @return array
     */
    protected function formatDefault(Content $content): array
    {
        return [
            'id' => $content->id,
            'name' => $content->name,
            'title' => $content->title,
            'description' => $content->description,
            'content' => $content->content,
            'published_at' => $content->published_at?->toDateTimeString(),
            'views_count' => $content->views_count,
            'created_at' => $content->created_at->toDateTimeString(),
            'updated_at' => $content->updated_at->toDateTimeString(),
        ];
    }

    /**
     * 获取支持的格式列表
     *
     * @return array
     */
    public function supportedFormats(): array
    {
        return ['default'];
    }

    /**
     * 处理内容版本
     *
     * @param ContentVersion $version
     * @return string
     */
    public function processVersion(ContentVersion $version): string
    {
        return $version->content;
    }

    /**
     * 获取内容的元数据定义
     *
     * @return array
     */
    public function metadataDefinitions(): array
    {
        return [];
    }

    /**
     * 获取内容的默认配置
     *
     * @return array
     */
    public function defaultConfig(): array
    {
        return [];
    }

    /**
     * 验证处理器配置
     *
     * @param array $config
     * @return bool
     */
    public function validateConfig(array $config): bool
    {
        return true;
    }

    /**
     * 获取内容的搜索索引数据
     *
     * @param Content $content
     * @return array
     */
    public function getSearchableData(Content $content): array
    {
        return [
            'title' => $content->title,
            'content' => strip_tags($content->content),
            'keywords' => $content->keywords,
            'description' => $content->description,
        ];
    }

    /**
     * 批量处理内容
     *
     * @param Collection $contents
     * @param array $options
     * @return Collection
     */
    public function batchProcess(Collection $contents, array $options = []): Collection
    {
        return $contents->map(function ($content) use ($options) {
            return $this->render($content, $options);
        });
    }
}
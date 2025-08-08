<?php

namespace Dybasedev\LunaPrototype\Content\Handlers;

use Dybasedev\LunaPrototype\Content\Models\Content;
use Dybasedev\LunaPrototype\Content\Results\ContentResult;

/**
 * 默认内容处理器
 * 
 * 提供基础的内容处理功能，不依赖任何第三方库
 */
class DefaultContentHandler extends BaseContentHandler
{
    /**
     * 获取处理器名称
     *
     * @return string
     */
    public function handlerName(): string
    {
        return '默认内容处理器';
    }

    /**
     * 获取处理器描述
     *
     * @return string
     */
    public function handlerDescription(): string
    {
        return '提供基础的内容处理功能，支持纯文本和基本的HTML内容';
    }

    /**
     * 内容渲染
     *
     * @param Content $content
     * @param array $options
     * @return ContentResult
     */
    public function render(Content $content, array $options = []): ContentResult
    {
        $result = new ContentResult();
        
        // 设置基础字段
        $result->setId($content->id)
            ->setName($content->name)
            ->setTitle($content->title)
            ->setDescription($content->description)
            ->setKeywords($content->keywords)
            ->setContent($this->processContent($content->content, $options))
            ->setPayload($content->payload)
            ->setPublishedAt($content->published_at)
            ->setViewsCount($content->views_count)
            ->setCreatedAt($content->created_at)
            ->setUpdatedAt($content->updated_at);
        
        // 处理分类和频道
        if ($content->relationLoaded('categories')) {
            $result->setCategories($content->categories->toArray());
        }
        
        if ($content->relationLoaded('channels')) {
            $result->setChannels($content->channels->toArray());
        }
        
        // 处理附件
        if ($content->relationLoaded('attachments')) {
            $result->setAttachments($content->attachments->toArray());
        }
        
        // 处理元数据
        if ($content->relationLoaded('metadata')) {
            $metadata = [];
            foreach ($content->metadata as $meta) {
                $metadata[$meta->key] = $meta->value;
            }
            $result->setMetadata($metadata);
        }
        
        return $result;
    }

    /**
     * 内容验证规则
     *
     * @return array
     */
    public function validationRules(): array
    {
        return [
            'content' => 'nullable|string',
            'payload' => 'nullable|array',
        ];
    }
    
    /**
     * 验证内容数据
     * 
     * @param array $data
     * @return array 验证错误信息，如果为空则表示验证通过
     */
    public function validateContent(array $data): array
    {
        $errors = [];
        
        // 可以在这里添加自定义的内容验证逻辑
        // 例如：检查内容长度、敏感词过滤等
        
        if (isset($data['content']) && is_string($data['content'])) {
            $contentLength = mb_strlen($data['content']);
            if ($contentLength > 100000) {
                $errors['content'] = '内容长度不能超过100000个字符';
            }
        }
        
        return $errors;
    }
    
    /**
     * 验证载荷数据
     * 
     * @param array $payload
     * @return array 验证错误信息
     */
    public function validatePayload(array $payload): array
    {
        // 默认处理器不对载荷做特殊验证
        // 子类可以重写此方法实现特定的载荷验证
        return [];
    }
    
    /**
     * 处理内容
     * 
     * @param string|null $content
     * @param array $options
     * @return string|null
     */
    protected function processContent(?string $content, array $options = []): ?string
    {
        if ($content === null) {
            return null;
        }
        
        // 基本的安全处理
        if ($options['strip_tags'] ?? false) {
            $content = strip_tags($content, $options['allowed_tags'] ?? '');
        }
        
        // 截断内容
        if (isset($options['max_length']) && $options['max_length'] > 0) {
            $content = mb_substr($content, 0, $options['max_length']);
        }
        
        return $content;
    }
}
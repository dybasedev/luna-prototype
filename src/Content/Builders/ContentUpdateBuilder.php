<?php

namespace Dybasedev\LunaPrototype\Content\Builders;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Content\Models\Content;
use Dybasedev\LunaPrototype\Content\LunaContent;
use Dybasedev\LunaPrototype\Content\Handlers\BaseContentHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * 内容更新构建器
 * 
 * 提供链式调用接口来更新内容，支持部分更新和验证
 * 
 * @package Dybasedev\LunaPrototype\Content\Builders
 */
class ContentUpdateBuilder
{
    /**
     * 要更新的内容实例
     */
    protected Content $content;
    
    /**
     * 待更新的字段数据
     */
    protected array $updates = [];
    
    /**
     * 编辑者信息
     */
    protected ?SessionHolder $editor = null;
    
    /**
     * 内容处理器实例
     */
    protected ?BaseContentHandler $handler = null;
    
    /**
     * LunaContent 实例
     */
    protected(set) LunaContent $lunaContent;
    
    public function __construct(Content $content)
    {
        $this->content = $content;
        $this->lunaContent = app(LunaContent::class);
        
        // 加载处理器
        if ($content->handler_id) {
            try {
                $this->handler = $this->lunaContent->handler->createHandlerInstance($content->handler_id);
            } catch (\Exception $e) {
                $this->handler = null;
            }
        }
    }
    
    /**
     * 创建更新构建器
     */
    public static function for(Content|int $content): static
    {
        if (!($content instanceof Content)) {
            $content = app(LunaContent::class)->configure->contentModel::findOrFail($content);
        }
        return new static($content);
    }
    
    /**
     * 从请求创建更新构建器
     */
    public static function fromRequest(Request $request, Content|int $content): static
    {
        $builder = static::for($content);
        
        // 只更新请求中存在的字段
        if ($request->has('title')) {
            $builder->title($request->input('title'));
        }
        if ($request->has('description')) {
            $builder->description($request->input('description'));
        }
        if ($request->has('keywords')) {
            $builder->keywords($request->input('keywords'));
        }
        if ($request->has('handler_id')) {
            $builder->handlerId($request->input('handler_id'));
        }
        if ($request->has('handler_config')) {
            $builder->handlerConfig($request->input('handler_config'));
        }
        if ($request->has('payload')) {
            $builder->payload($request->input('payload'));
        }
        if ($request->has('content')) {
            $builder->content(
                $request->input('content'),
                $request->input('version_name'),
                $request->input('version_note')
            );
        }
        if ($request->has('category_ids')) {
            $builder->categoryIds($request->input('category_ids'));
        }
        if ($request->has('channel_ids')) {
            $builder->channelIds($request->input('channel_ids'));
        }
        if ($request->has('published')) {
            $builder->published($request->boolean('published'));
        }
        
        return $builder;
    }
    
    /**
     * 设置编辑者
     */
    public function editor(?SessionHolder $editor): static
    {
        $this->editor = $editor;
        return $this;
    }
    
    /**
     * 更新标题
     */
    public function title(string $title): static
    {
        $this->updates['title'] = $title;
        return $this;
    }
    
    /**
     * 更新描述
     */
    public function description(string $description): static
    {
        $this->updates['description'] = $description;
        return $this;
    }
    
    /**
     * 更新关键词
     */
    public function keywords(?string $keywords): static
    {
        $this->updates['keywords'] = $keywords;
        return $this;
    }
    
    /**
     * 更新处理器
     */
    public function handlerId(?int $handlerId): static
    {
        $this->updates['handler_id'] = $handlerId;
        if ($handlerId && $handlerId !== $this->content->handler_id) {
            try {
                $this->handler = $this->lunaContent->handler->createHandlerInstance($handlerId);
            } catch (\Exception $e) {
                $this->handler = null;
            }
        }
        return $this;
    }
    
    /**
     * 更新处理器配置
     */
    public function handlerConfig(?array $config): static
    {
        $this->updates['handler_config'] = $config;
        return $this;
    }
    
    /**
     * 更新载荷数据
     */
    public function payload(array $payload): static
    {
        $this->updates['payload'] = $payload;
        return $this;
    }
    
    /**
     * 合并载荷数据
     */
    public function mergePayload(array $payload): static
    {
        $this->updates['payload'] = array_merge(
            $this->content->payload ?? [],
            $this->updates['payload'] ?? [],
            $payload
        );
        return $this;
    }
    
    /**
     * 更新内容（创建新版本）
     */
    public function content(string $content, ?string $versionName = null, ?string $versionNote = null): static
    {
        $this->updates['content'] = $content;
        $this->updates['version_name'] = $versionName;
        $this->updates['version_note'] = $versionNote;
        return $this;
    }
    
    /**
     * 更新分类
     */
    public function categoryIds(array $categoryIds): static
    {
        $this->updates['categories'] = $categoryIds;
        return $this;
    }
    
    /**
     * 添加分类
     */
    public function addCategory(int $categoryId): static
    {
        if (!isset($this->updates['categories'])) {
            $this->updates['categories'] = $this->content->categories->pluck('id')->toArray();
        }
        $this->updates['categories'][] = $categoryId;
        $this->updates['categories'] = array_unique($this->updates['categories']);
        return $this;
    }
    
    /**
     * 移除分类
     */
    public function removeCategory(int $categoryId): static
    {
        if (!isset($this->updates['categories'])) {
            $this->updates['categories'] = $this->content->categories->pluck('id')->toArray();
        }
        $this->updates['categories'] = array_diff($this->updates['categories'], [$categoryId]);
        return $this;
    }
    
    /**
     * 更新频道
     */
    public function channelIds(array $channelIds): static
    {
        $this->updates['channels'] = $channelIds;
        return $this;
    }
    
    /**
     * 设置发布状态
     */
    public function published(bool $published = true): static
    {
        $this->updates['published'] = $published;
        return $this;
    }
    
    /**
     * 发布内容
     */
    public function publish(): static
    {
        return $this->published(true);
    }
    
    /**
     * 取消发布
     */
    public function unpublish(): static
    {
        return $this->published(false);
    }
    
    /**
     * 获取验证规则
     */
    public function getValidationRules(): array
    {
        $rules = [];
        
        // 根据实际要更新的字段添加验证规则
        if (isset($this->updates['title'])) {
            $rules['title'] = 'required|string|max:255';
        }
        if (isset($this->updates['description'])) {
            $rules['description'] = 'nullable|string|max:1000';
        }
        if (isset($this->updates['keywords'])) {
            $rules['keywords'] = 'nullable|string|max:1000';
        }
        if (isset($this->updates['handler_id'])) {
            $rules['handler_id'] = 'nullable|integer|exists:luna_handlers,id';
        }
        if (isset($this->updates['handler_config'])) {
            $rules['handler_config'] = 'nullable|array';
        }
        if (isset($this->updates['payload'])) {
            $rules['payload'] = 'nullable|array';
        }
        if (isset($this->updates['content'])) {
            $rules['content'] = 'nullable|string';
        }
        if (isset($this->updates['version_name'])) {
            $rules['version_name'] = 'nullable|string|max:255';
        }
        if (isset($this->updates['version_note'])) {
            $rules['version_note'] = 'nullable|string|max:1000';
        }
        if (isset($this->updates['categories'])) {
            $rules['categories'] = 'nullable|array';
            $rules['categories.*'] = 'integer|exists:luna_content_categories,id';
        }
        if (isset($this->updates['channels'])) {
            $rules['channels'] = 'nullable|array';
            $rules['channels.*'] = 'integer|exists:luna_content_channels,id';
        }
        
        // 如果有处理器，合并处理器的验证规则
        if ($this->handler instanceof BaseContentHandler) {
            $handlerRules = $this->handler->validationRules();
            foreach ($handlerRules as $field => $rule) {
                if (str_starts_with($field, 'payload.') && isset($this->updates['payload'])) {
                    $rules[$field] = $rule;
                } elseif ($field === 'content' && isset($this->updates['content'])) {
                    $rules['content'] = $rules['content'] . '|' . $rule;
                }
            }
        }
        
        return $rules;
    }
    
    /**
     * 验证更新数据
     * 
     * @throws ValidationException
     */
    public function validate(): static
    {
        if (empty($this->updates)) {
            return $this;
        }
        
        $rules = $this->getValidationRules();
        
        if (!empty($rules)) {
            $validator = Validator::make($this->updates, $rules);
            
            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
        }
        
        return $this;
    }
    
    /**
     * 检查是否有更新
     */
    public function hasUpdates(): bool
    {
        return !empty($this->updates);
    }
    
    /**
     * 获取更新数据
     */
    public function getUpdates(): array
    {
        return $this->updates;
    }
    
    /**
     * 保存更新
     * 
     * @param bool $validate 是否验证数据
     * @return Content
     * @throws ValidationException
     */
    public function save(bool $validate = true): Content
    {
        if (!$this->hasUpdates()) {
            return $this->content;
        }
        
        if ($validate) {
            $this->validate();
        }
        
        return $this->lunaContent->updateContent($this->content, $this->updates, $this->editor);
    }
}
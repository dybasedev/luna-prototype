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
 * 内容构建器
 * 
 * 提供链式调用接口来构建内容，支持数据验证和处理器集成
 * 
 * @package Dybasedev\LunaPrototype\Content\Builders
 */
class ContentBuilder
{
    /**
     * 内容唯一标识符
     */
    protected string $name;
    
    /**
     * 内容标题
     */
    protected string $title;
    
    /**
     * 内容描述
     */
    protected string $description = '';
    
    /**
     * 关键词
     */
    protected ?string $keywords = null;
    
    /**
     * 处理器ID、类名或别名
     */
    protected string|int $handler;
    
    /**
     * 处理器配置
     */
    protected ?array $handlerConfig = null;
    
    /**
     * 载荷数据
     */
    protected array $payload = [];
    
    /**
     * 内容所有者
     */
    protected ?SessionHolder $owner = null;
    
    /**
     * 内容正文
     */
    protected ?string $content = null;
    
    /**
     * 版本名称
     */
    protected ?string $versionName = null;
    
    /**
     * 版本说明
     */
    protected ?string $versionNote = null;
    
    /**
     * 分类ID列表
     */
    protected array $categoryIds = [];
    
    /**
     * 频道ID列表
     */
    protected array $channelIds = [];
    
    /**
     * 发布状态
     */
    protected bool $published = false;
    
    /**
     * 浏览次数（用于数据迁移等特殊场景）
     */
    protected ?int $viewsCount = null;
    
    /**
     * 处理器实例
     */
    protected ?BaseContentHandler $handlerInstance = null;
    
    /**
     * LunaContent 实例
     */
    protected(set) LunaContent $lunaContent;
    
    public function __construct()
    {
        $this->lunaContent = app(LunaContent::class);
    }
    
    /**
     * 创建新的构建器实例
     */
    public static function create(): static
    {
        return new static();
    }
    
    /**
     * 从请求创建构建器
     */
    public static function fromRequest(Request $request): static
    {
        $builder = new static();
        
        return $builder
            ->name($request->input('name'))
            ->title($request->input('title'))
            ->description($request->input('description', ''))
            ->keywords($request->input('keywords'))
            ->handler($request->input('handler_id') ?? $request->input('handler_class'))
            ->handlerConfig($request->input('handler_config'))
            ->payload($request->input('payload', []))
            ->content($request->input('content'))
            ->versionName($request->input('version_name'))
            ->versionNote($request->input('version_note'))
            ->categoryIds($request->input('category_ids', []))
            ->channelIds($request->input('channel_ids', []))
            ->published($request->boolean('published'));
    }
    
    /**
     * 设置内容唯一标识符
     */
    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }
    
    /**
     * 设置内容标题
     */
    public function title(string $title): static
    {
        $this->title = $title;
        return $this;
    }
    
    /**
     * 设置内容描述
     */
    public function description(string $description): static
    {
        $this->description = $description;
        return $this;
    }
    
    /**
     * 设置关键词
     */
    public function keywords(?string $keywords): static
    {
        $this->keywords = $keywords;
        return $this;
    }
    
    /**
     * 设置处理器
     * 
     * @param string|int $handler 处理器ID、类名或别名
     */
    public function handler(string|int $handler): static
    {
        $this->handler = $handler;
        // 尝试预加载处理器以进行验证
        try {
            $this->handlerInstance = luna_handler()->createHandlerInstance($handler);
        } catch (\Exception $e) {
            // 处理器可能还不存在，将在保存时创建
            $this->handlerInstance = null;
        }
        return $this;
    }
    
    /**
     * 设置处理器配置
     */
    public function handlerConfig(?array $config): static
    {
        $this->handlerConfig = $config;
        // 如果已经设置了处理器，重新创建带配置的处理器
        if (isset($this->handler)) {
            try {
                $this->handlerInstance = luna_handler()->createHandlerInstance($this->handler);
                if ($this->handlerInstance && $config) {
                    $this->handlerInstance->withConfig($config);
                }
            } catch (\Exception $e) {
                $this->handlerInstance = null;
            }
        }
        return $this;
    }
    
    /**
     * 设置载荷数据
     */
    public function payload(array $payload): static
    {
        $this->payload = $payload;
        return $this;
    }
    
    /**
     * 设置所有者
     */
    public function owner(?SessionHolder $owner): static
    {
        $this->owner = $owner;
        return $this;
    }
    
    /**
     * 设置内容正文
     */
    public function content(?string $content): static
    {
        $this->content = $content;
        return $this;
    }
    
    /**
     * 设置版本名称
     */
    public function versionName(?string $versionName): static
    {
        $this->versionName = $versionName;
        return $this;
    }
    
    /**
     * 设置版本说明
     */
    public function versionNote(?string $versionNote): static
    {
        $this->versionNote = $versionNote;
        return $this;
    }
    
    /**
     * 设置分类ID
     */
    public function categoryIds(array $categoryIds): static
    {
        $this->categoryIds = $categoryIds;
        return $this;
    }
    
    /**
     * 添加分类
     */
    public function addCategory(int $categoryId): static
    {
        $this->categoryIds[] = $categoryId;
        return $this;
    }
    
    /**
     * 设置频道ID
     */
    public function channelIds(array $channelIds): static
    {
        $this->channelIds = $channelIds;
        return $this;
    }
    
    /**
     * 添加频道
     */
    public function addChannel(int $channelId): static
    {
        $this->channelIds[] = $channelId;
        return $this;
    }
    
    /**
     * 设置发布状态
     */
    public function published(bool $published = true): static
    {
        $this->published = $published;
        return $this;
    }
    
    /**
     * 设置为已发布
     */
    public function publish(): static
    {
        return $this->published(true);
    }
    
    /**
     * 设置为草稿
     */
    public function draft(): static
    {
        return $this->published(false);
    }
    
    /**
     * 设置浏览次数（用于数据迁移等特殊场景）
     */
    public function viewsCount(int $count): static
    {
        $this->viewsCount = $count;
        return $this;
    }
    
    /**
     * 获取验证规则
     */
    public function getValidationRules(): array
    {
        $rules = [
            'name' => 'required|string|max:255|unique:luna_contents,name',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'keywords' => 'nullable|string|max:1000',
            'handler_id' => 'required',
            'handler_config' => 'nullable|array',
            'payload' => 'nullable|array',
            'content' => 'nullable|string',
            'version_name' => 'nullable|string|max:255',
            'version_note' => 'nullable|string|max:1000',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:luna_content_categories,id',
            'channel_ids' => 'nullable|array',
            'channel_ids.*' => 'integer|exists:luna_content_channels,id',
            'published' => 'boolean',
        ];
        
        // 如果有处理器，合并处理器的验证规则
        if ($this->handlerInstance instanceof BaseContentHandler) {
            $handlerRules = $this->handlerInstance->validationRules();
            // 处理器规则主要针对 payload 和 content
            foreach ($handlerRules as $field => $rule) {
                if (str_starts_with($field, 'payload.')) {
                    $rules[$field] = $rule;
                } elseif ($field === 'content') {
                    // 处理器可能对内容有特殊要求
                    $rules['content'] = $rules['content'] . '|' . $rule;
                }
            }
        }
        
        return $rules;
    }
    
    /**
     * 验证数据
     * 
     * @throws ValidationException
     */
    public function validate(): static
    {
        $data = $this->toArray();
        $rules = $this->getValidationRules();
        
        $validator = Validator::make($data, $rules);
        
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        
        // 如果有处理器，执行处理器的自定义验证
        if ($this->handlerInstance instanceof BaseContentHandler) {
            // 验证内容
            if (isset($data['content'])) {
                $contentErrors = $this->handlerInstance->validateContent($data);
                if (!empty($contentErrors)) {
                    $validator->after(function ($validator) use ($contentErrors) {
                        foreach ($contentErrors as $field => $message) {
                            $validator->errors()->add($field, $message);
                        }
                    });
                }
            }
            
            // 验证载荷
            if (isset($data['payload']) && is_array($data['payload'])) {
                $payloadErrors = $this->handlerInstance->validatePayload($data['payload']);
                if (!empty($payloadErrors)) {
                    $validator->after(function ($validator) use ($payloadErrors) {
                        foreach ($payloadErrors as $field => $message) {
                            $validator->errors()->add('payload.' . $field, $message);
                        }
                    });
                }
            }
            
            // 如果有自定义错误，重新抛出异常
            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
        }
        
        return $this;
    }
    
    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name ?? '',
            'title' => $this->title ?? '',
            'description' => $this->description,
            'keywords' => $this->keywords,
            'handler_id' => $this->handler ?? '',
            'handler_config' => $this->handlerConfig,
            'payload' => $this->payload,
            'content' => $this->content,
            'version_name' => $this->versionName,
            'version_note' => $this->versionNote,
            'categories' => $this->categoryIds,
            'channels' => $this->channelIds,
            'published' => $this->published,
        ];
        
        if ($this->viewsCount !== null) {
            $data['views_count'] = $this->viewsCount;
        }
        
        return $data;
    }
    
    /**
     * 创建内容
     * 
     * @param bool $validate 是否验证数据
     * @return Content
     * @throws ValidationException
     * @throws \RuntimeException
     */
    public function save(bool $validate = true): Content
    {
        if ($validate) {
            $this->validate();
        }
        
        // 确保处理器已设置
        if (!isset($this->handler)) {
            throw new \RuntimeException('必须指定内容处理器');
        }
        
        // 获取处理器ID
        $handlerId = is_numeric($this->handler) ? $this->handler : $this->resolveHandlerId($this->handler);
        
        // 准备创建内容的数据
        $data = $this->toArray();
        
        return $this->lunaContent->createContent($data, $this->owner);
    }
    
    /**
     * 构建但不保存（用于预览等场景）
     */
    public function build(): array
    {
        return $this->toArray();
    }
    
    /**
     * 解析处理器ID
     * 
     * @param string $handler 处理器类名或别名
     * @return int 处理器ID
     * @throws \RuntimeException
     */
    protected function resolveHandlerId(string $handler): int
    {
        // 性能优化：首先尝试作为 pure handler 处理
        try {
            // 尝试通过别名获取处理器类名
            $handlerClass = luna_handler()->getHandlerClassByAlias($handler);
            
            if ($handlerClass && !$handlerClass::requiresEntity()) {
                // 是 pure handler，返回其类名的 hash code
                return hash_code($handlerClass);
            }
            
            // 如果不是别名，尝试作为类名处理
            if (!$handlerClass && class_exists($handler)) {
                $handlerInstance = app()->make($handler);
                if (!$handlerInstance::requiresEntity()) {
                    // 是 pure handler
                    return hash_code($handler);
                }
            }
        } catch (\Exception $e) {
            // 不是 pure handler，继续尝试其他方式
        }
        
        // 尝试作为实体处理器查找
        $entity = luna_handler()->entityHandler($handler);
        if ($entity) {
            return $entity->id;
        }
        
        // 如果都不是，抛出异常
        throw new \RuntimeException(sprintf('无法解析内容处理器 "%s"：既不是已注册的 pure handler，也不是已存在的 entity handler', $handler));
    }
}
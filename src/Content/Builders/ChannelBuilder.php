<?php

namespace Dybasedev\LunaPrototype\Content\Builders;

use Dybasedev\LunaPrototype\Content\Models\ContentChannel;
use Dybasedev\LunaPrototype\Content\LunaContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * 频道构建器
 * 
 * 提供链式调用接口来创建或更新频道
 * 
 * @package Dybasedev\LunaPrototype\Content\Builders
 */
class ChannelBuilder
{
    /**
     * 频道唯一标识符
     */
    protected string $name;
    
    /**
     * 频道显示名称
     */
    protected string $displayName;
    
    /**
     * 频道描述
     */
    protected string $description = '';
    
    /**
     * 处理器ID
     */
    protected ?int $handlerId = null;
    
    /**
     * 频道配置
     */
    protected ?array $config = null;
    
    /**
     * 是否激活
     */
    protected bool $isActive = true;
    
    /**
     * 排序值
     */
    protected int $sort = 0;
    
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
            ->displayName($request->input('display_name'))
            ->description($request->input('description', ''))
            ->handlerId($request->input('handler_id'))
            ->config($request->input('config'))
            ->isActive($request->boolean('is_active', true))
            ->sort($request->integer('sort', 0));
    }
    
    /**
     * 设置频道唯一标识符
     */
    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }
    
    /**
     * 设置频道显示名称
     */
    public function displayName(string $displayName): static
    {
        $this->displayName = $displayName;
        return $this;
    }
    
    /**
     * 设置频道描述
     */
    public function description(string $description): static
    {
        $this->description = $description;
        return $this;
    }
    
    /**
     * 设置处理器ID
     */
    public function handlerId(?int $handlerId): static
    {
        $this->handlerId = $handlerId;
        return $this;
    }
    
    /**
     * 设置频道配置
     */
    public function config(?array $config): static
    {
        $this->config = $config;
        return $this;
    }
    
    /**
     * 设置特定配置项
     */
    public function setConfig(string $key, mixed $value): static
    {
        if ($this->config === null) {
            $this->config = [];
        }
        $this->config[$key] = $value;
        return $this;
    }
    
    /**
     * 设置是否激活
     */
    public function isActive(bool $active = true): static
    {
        $this->isActive = $active;
        return $this;
    }
    
    /**
     * 激活频道
     */
    public function activate(): static
    {
        return $this->isActive(true);
    }
    
    /**
     * 停用频道
     */
    public function deactivate(): static
    {
        return $this->isActive(false);
    }
    
    /**
     * 设置排序
     */
    public function sort(int $sort): static
    {
        $this->sort = $sort;
        return $this;
    }
    
    /**
     * 获取验证规则
     */
    public function getValidationRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'handler_id' => 'nullable|integer|exists:luna_handlers,id',
            'config' => 'nullable|array',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
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
        
        return $this;
    }
    
    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name ?? '',
            'display_name' => $this->displayName ?? $this->name ?? '',
            'description' => $this->description,
            'handler_id' => $this->handlerId,
            'config' => $this->config,
            'is_active' => $this->isActive,
            'sort' => $this->sort,
        ];
    }
    
    /**
     * 保存（创建或更新）
     * 
     * @param bool $validate 是否验证数据
     * @return ContentChannel
     * @throws ValidationException
     */
    public function save(bool $validate = true): ContentChannel
    {
        if ($validate) {
            $this->validate();
        }
        
        if (!isset($this->name)) {
            throw new \InvalidArgumentException('Channel name is required');
        }
        
        return $this->lunaContent->createOrUpdateChannel($this->name, $this->toArray());
    }
    
    /**
     * 构建但不保存
     */
    public function build(): array
    {
        return $this->toArray();
    }
}
<?php

namespace Dybasedev\LunaPrototype\Content\Results;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

/**
 * 内容结果类
 * 
 * 用于统一内容处理器的输出格式
 */
class ContentResult implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * 内容ID
     */
    protected int $id;
    
    /**
     * 内容唯一标识
     */
    protected string $name;
    
    /**
     * 内容标题
     */
    protected string $title;
    
    /**
     * 内容描述
     */
    protected ?string $description = null;
    
    /**
     * 关键词
     */
    protected ?string $keywords = null;
    
    /**
     * 内容正文
     */
    protected ?string $content = null;
    
    /**
     * 载荷数据
     */
    protected array $payload = [];
    
    /**
     * 发布时间
     */
    protected ?\DateTimeInterface $publishedAt = null;
    
    /**
     * 浏览次数
     */
    protected int $viewsCount = 0;
    
    /**
     * 创建时间
     */
    protected \DateTimeInterface $createdAt;
    
    /**
     * 更新时间
     */
    protected \DateTimeInterface $updatedAt;
    
    /**
     * 分类信息
     */
    protected array $categories = [];
    
    /**
     * 频道信息
     */
    protected array $channels = [];
    
    /**
     * 附件信息
     */
    protected array $attachments = [];
    
    /**
     * 元数据
     */
    protected array $metadata = [];
    
    /**
     * 自定义字段
     */
    protected array $customFields = [];
    
    // Setters with fluent interface
    
    public function setId(int $id): static
    {
        $this->id = $id;
        return $this;
    }
    
    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }
    
    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }
    
    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }
    
    public function setKeywords(?string $keywords): static
    {
        $this->keywords = $keywords;
        return $this;
    }
    
    public function setContent(?string $content): static
    {
        $this->content = $content;
        return $this;
    }
    
    public function setPayload(array $payload): static
    {
        $this->payload = $payload;
        return $this;
    }
    
    public function setPublishedAt(?\DateTimeInterface $publishedAt): static
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }
    
    public function setViewsCount(int $viewsCount): static
    {
        $this->viewsCount = $viewsCount;
        return $this;
    }
    
    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
    
    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
    
    public function setCategories(array $categories): static
    {
        $this->categories = $categories;
        return $this;
    }
    
    public function setChannels(array $channels): static
    {
        $this->channels = $channels;
        return $this;
    }
    
    public function setAttachments(array $attachments): static
    {
        $this->attachments = $attachments;
        return $this;
    }
    
    public function setMetadata(array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }
    
    public function setCustomField(string $key, mixed $value): static
    {
        $this->customFields[$key] = $value;
        return $this;
    }
    
    public function setCustomFields(array $fields): static
    {
        $this->customFields = $fields;
        return $this;
    }
    
    // Getters
    
    public function getId(): int
    {
        return $this->id;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getTitle(): string
    {
        return $this->title;
    }
    
    public function getDescription(): ?string
    {
        return $this->description;
    }
    
    public function getKeywords(): ?string
    {
        return $this->keywords;
    }
    
    public function getContent(): ?string
    {
        return $this->content;
    }
    
    public function getPayload(): array
    {
        return $this->payload;
    }
    
    public function getPublishedAt(): ?\DateTimeInterface
    {
        return $this->publishedAt;
    }
    
    public function getViewsCount(): int
    {
        return $this->viewsCount;
    }
    
    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
    
    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }
    
    public function getCategories(): array
    {
        return $this->categories;
    }
    
    public function getChannels(): array
    {
        return $this->channels;
    }
    
    public function getAttachments(): array
    {
        return $this->attachments;
    }
    
    public function getMetadata(): array
    {
        return $this->metadata;
    }
    
    public function getCustomField(string $key, mixed $default = null): mixed
    {
        return $this->customFields[$key] ?? $default;
    }
    
    public function getCustomFields(): array
    {
        return $this->customFields;
    }
    
    /**
     * 转换为数组
     * 
     * @return array
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'description' => $this->description,
            'keywords' => $this->keywords,
            'content' => $this->content,
            'payload' => $this->payload,
            'published_at' => $this->publishedAt?->format('Y-m-d H:i:s'),
            'views_count' => $this->viewsCount,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
        
        // 只包含非空的关联数据
        if (!empty($this->categories)) {
            $data['categories'] = $this->categories;
        }
        
        if (!empty($this->channels)) {
            $data['channels'] = $this->channels;
        }
        
        if (!empty($this->attachments)) {
            $data['attachments'] = $this->attachments;
        }
        
        if (!empty($this->metadata)) {
            $data['metadata'] = $this->metadata;
        }
        
        // 合并自定义字段
        return array_merge($data, $this->customFields);
    }
    
    /**
     * 转换为 JSON
     * 
     * @param int $options
     * @return string
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }
    
    /**
     * 用于 JSON 序列化
     * 
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
    
    /**
     * 检查内容是否已发布
     * 
     * @return bool
     */
    public function isPublished(): bool
    {
        return $this->publishedAt !== null && $this->publishedAt <= new \DateTime();
    }
    
    /**
     * 获取摘要
     * 
     * @param int $length
     * @param string $suffix
     * @return string
     */
    public function getSummary(int $length = 200, string $suffix = '...'): string
    {
        if ($this->description) {
            return $this->description;
        }
        
        if ($this->content) {
            $text = strip_tags($this->content);
            if (mb_strlen($text) > $length) {
                return mb_substr($text, 0, $length) . $suffix;
            }
            return $text;
        }
        
        return '';
    }
}
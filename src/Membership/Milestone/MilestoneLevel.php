<?php

namespace Dybasedev\LunaPrototype\Membership\Milestone;

/**
 * 里程碑等级定义
 * 
 * 用于定义单个里程碑等级的信息，包括标识符、显示名称、顺序等
 */
readonly class MilestoneLevel
{
    /**
     * @param string $identifier 里程碑标识符
     * @param string $displayName 显示名称
     * @param int $sequence 顺序（用于排序）
     * @param array $metadata 额外参数（如图标、描述、文案等）
     */
    public function __construct(
        public string $identifier,
        public string $displayName,
        public int $sequence,
        public array $metadata = []
    ) {
    }

    /**
     * 获取元数据中的特定值
     *
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * 转换为数组
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'display_name' => $this->displayName,
            'sequence' => $this->sequence,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * 使用配置覆盖创建新的里程碑等级实例
     * 
     * 允许通过配置覆盖显示名称、顺序和元数据等非必填信息
     * 标识符是核心信息不允许修改
     *
     * @param array $overrides 覆盖配置
     * @return static
     */
    public function withOverrides(array $overrides): static
    {
        return new static(
            identifier: $this->identifier,
            displayName: $overrides['display_name'] ?? $this->displayName,
            sequence: $overrides['sequence'] ?? $this->sequence,
            metadata: array_merge($this->metadata, $overrides['metadata'] ?? [])
        );
    }

    /**
     * 从配置数组创建里程碑等级
     * 
     * 支持完整配置和仅标识符两种方式
     *
     * @param string|array $config
     * @param int|null $defaultSequence
     * @return static
     */
    public static function fromConfig(string|array $config, ?int $defaultSequence = null): static
    {
        if (is_string($config)) {
            return new static(
                identifier: $config,
                displayName: $config,
                sequence: $defaultSequence ?? 0
            );
        }

        return new static(
            identifier: $config['identifier'] ?? throw new \InvalidArgumentException('Milestone identifier is required'),
            displayName: $config['display_name'] ?? $config['identifier'],
            sequence: $config['sequence'] ?? $defaultSequence ?? 0,
            metadata: $config['metadata'] ?? []
        );
    }
}
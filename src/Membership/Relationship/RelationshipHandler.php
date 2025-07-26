<?php

namespace Dybasedev\LunaPrototype\Membership\Relationship;

use Dybasedev\LunaPrototype\Foundation\Handler\BaseHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\ModelHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\WithModelHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\WithModelInstance;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;

/**
 * 会员关系处理器基类
 * 
 * 定义会员关系类型的基本行为和接口
 */
abstract class RelationshipHandler extends BaseHandler implements ModelHandler
{
    use WithModelHandler, WithModelInstance;

    /**
     * 获取关系类型唯一标识
     * 
     * @return string
     */
    abstract public function getTypeKey(): string;

    /**
     * 获取关系类型显示名称
     * 
     * @return string
     */
    abstract public function getDisplayName(): string;

    /**
     * 获取关系类型描述
     * 
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return null;
    }

    /**
     * 是否允许多级关系
     * 
     * @return bool
     */
    public function allowsMultiLevel(): bool
    {
        return true;
    }

    /**
     * 获取最大层级深度（0表示无限制）
     * 
     * @return int
     */
    public function getMaxDepth(): int
    {
        return 0;
    }

    /**
     * 是否允许修改关系
     * 
     * @return bool
     */
    public function allowsModification(): bool
    {
        return false;
    }

    /**
     * 加入关系链前的验证
     * 
     * @param SessionHolder $parent 上级成员
     * @param SessionHolder $child 下级成员
     * @param array $context 上下文数据
     * @return bool
     */
    public function validateJoin(SessionHolder $parent, SessionHolder $child, array $context = []): bool
    {
        return true;
    }

    /**
     * 加入关系链时的处理逻辑
     * 
     * @param SessionHolder $parent 上级成员
     * @param SessionHolder $child 下级成员
     * @param array $context 上下文数据
     * @return void
     */
    public function onJoin(SessionHolder $parent, SessionHolder $child, array $context = []): void
    {
        // 子类可以重写此方法实现具体业务逻辑
    }

    /**
     * 离开关系链时的处理逻辑
     * 
     * @param SessionHolder $parent 原上级成员
     * @param SessionHolder $child 下级成员
     * @param array $context 上下文数据
     * @return void
     */
    public function onLeave(SessionHolder $parent, SessionHolder $child, array $context = []): void
    {
        // 子类可以重写此方法实现具体业务逻辑
    }

    /**
     * 关系链变更时的处理逻辑（如更换上级）
     * 
     * @param SessionHolder $oldParent 原上级成员
     * @param SessionHolder $newParent 新上级成员
     * @param SessionHolder $child 下级成员
     * @param array $context 上下文数据
     * @return void
     */
    public function onChange(SessionHolder $oldParent, SessionHolder $newParent, SessionHolder $child, array $context = []): void
    {
        // 子类可以重写此方法实现具体业务逻辑
    }

    /**
     * 获取关系类型的默认配置
     * 
     * @return array
     */
    public function getDefaultConfig(): array
    {
        return [
            'allows_multi_level' => $this->allowsMultiLevel(),
            'max_depth' => $this->getMaxDepth(),
            'allows_modification' => $this->allowsModification(),
        ];
    }

    /**
     * 处理模型实例（ModelHandler 接口实现）
     * 
     * @param mixed ...$parameters
     * @return mixed
     */
    public function handle(...$parameters): mixed
    {
        // 默认返回关系类型信息
        return [
            'type_key' => $this->getTypeKey(),
            'display_name' => $this->getDisplayName(),
            'description' => $this->getDescription(),
            'config' => $this->getDefaultConfig(),
        ];
    }

    /**
     * 获取处理器名称（BaseHandler 抽象方法实现）
     * 
     * @return string
     */
    public function handlerName(): string
    {
        return $this->getDisplayName();
    }

    /**
     * 获取处理器描述（BaseHandler 抽象方法实现）
     * 
     * @return string
     */
    public function handlerDescription(): string
    {
        return $this->getDescription() ?? '';
    }
}
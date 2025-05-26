<?php

namespace Dybasedev\LunaPrototype\Foundation;

/**
 * 会话持有人接口
 */
interface SessionHolder
{
    /**
     * 获取操作人类型名称
     *
     * @return string
     */
    public function getOperatorTypeName(): string;

    /**
     * 获取操作人类型 ID，一般是操作人类型通过 hash 生成的
     *
     * @return int
     */
    public function getOperatorType(): int;

    /**
     * 获取操作人 ID，一般是主键 ID
     *
     * @return int
     */
    public function getOperatorId(): int;

    /**
     * 获取会话持有人上下文信息
     *
     * 根据业务需要实现，用于诸如权限控制等场景
     *
     * @return array|null
     */
    public function getSessionHolderContext(): ?array;
}
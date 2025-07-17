<?php

namespace Dybasedev\LunaPrototype\Foundation;

/**
 * 会话持有人接口
 *
 * 这个接口定义了会话持有人的标准契约，用于在系统中标识和管理用户会话。
 * 会话持有人可以是用户、管理员或其他类型的操作者。
 *
 * 接口的设计目的：
 * - 统一会话管理方式
 * - 支持多种类型的操作者
 * - 提供权限控制的基础信息
 * - 支持审计日志记录
 *
 * 实现此接口的类应该：
 * - 提供操作者的基本身份信息
 * - 支持类型化的操作者识别
 * - 可选地提供上下文信息用于业务逻辑
 *
 * @package Dybasedev\LunaPrototype\Foundation
 * @author Luna Prototype Team
 * @since 1.0.0
 */
interface SessionHolder
{
    /**
     * 获取操作人类型名称
     *
     * 返回操作人类型的字符串表示，用于日志记录和显示。
     * 这个名称应该是人类可读的，如 'user', 'admin', 'system' 等。
     *
     * @return string 操作人类型的名称
     */
    public function getOperatorTypeName(): string;

    /**
     * 获取操作人类型 ID
     *
     * 返回操作人类型的数字 ID，通常是通过 hash_code 函数生成的。
     * 这个 ID 用于在数据库中存储和查询操作人类型。
     *
     * @return int 操作人类型的数字 ID
     */
    public function getOperatorType(): int;

    /**
     * 获取操作人 ID
     *
     * 返回操作人的唯一标识符，通常是数据库中的主键 ID。
     * 这个 ID 用于唯一标识一个具体的操作者。
     *
     * @return int 操作人的唯一标识符
     */
    public function getOperatorId(): int;

    /**
     * 获取会话持有人上下文信息
     *
     * 返回与当前会话相关的上下文信息，可以包括：
     * - 权限信息
     * - 用户偏好设置
     * - 会话状态信息
     * - 业务相关的上下文数据
     *
     * 这个方法的返回值会被用于：
     * - 权限控制逻辑
     * - 业务规则判断
     * - 审计日志记录
     *
     * @return array|null 上下文信息数组，如果没有上下文信息则返回 null
     */
    public function getSessionHolderContext(): ?array;
}
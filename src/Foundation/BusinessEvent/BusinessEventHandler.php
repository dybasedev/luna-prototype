<?php

namespace Dybasedev\LunaPrototype\Foundation\BusinessEvent;

use Dybasedev\LunaPrototype\Foundation\Handler\BaseHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\ModelHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\WithModelInstance;

/**
 * 业务事件处理器基类
 * 
 * 继承自 BaseHandler，提供业务事件的格式化和处理功能。
 * 每个具体的业务事件处理器需要实现数据格式化的抽象方法。
 * 
 * 业务事件是系统中重要操作的记录，可以用于：
 * - 审计日志
 * - 操作历史记录
 * - 业务流程跟踪
 * - 通知和消息推送
 * 
 * @package Dybasedev\LunaPrototype\Foundation\BusinessEvent
 */
abstract class BusinessEventHandler extends BaseHandler implements ModelHandler
{
    use WithModelInstance;

    /**
     * 将数据格式化为文本
     * 
     * 将业务事件的载荷数据转换为可读的文本格式。
     * 用于生成日志信息、通知内容或其他文本展示。
     *
     * @param array $payload 业务事件的载荷数据
     * @param string|null $format 格式化类型，如 'simple'、'detailed'、'markdown' 等
     * @param array $context 格式化时的额外上下文，可以通过这个传递如业务场景等信息，采取不同的格式化逻辑
     * @return string 格式化后的文本
     */
    abstract public function formatPayloadToText(array $payload, ?string $format = null, array $context = []): string;

    /**
     * 将数据格式化为视图数据
     * 
     * 将业务事件的载荷数据转换为适合前端展示的结构化数据。
     * 用于生成页面展示、API 响应或其他结构化输出。
     *
     * @param array $payload 业务事件的载荷数据
     * @param string|null $format 格式化类型，如 'list'、'detail'、'card' 等
     * @param array $context 格式化时的额外上下文，可以通过这个传递如业务场景等信息，采取不同的格式化逻辑
     * @return array|null 格式化后的视图数据，返回 null 表示无法格式化
     */
    abstract public function formatPayloadToViewData(array $payload, ?string $format = null, array $context = []): ?array;
}
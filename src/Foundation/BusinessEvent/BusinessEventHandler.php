<?php

namespace Dybasedev\LunaPrototype\Foundation\BusinessEvent;

use Dybasedev\LunaPrototype\Foundation\Handler\BaseHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\ModelHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\WithModelInstance;

/**
 * 业务事件处理器基类
 */
abstract class BusinessEventHandler extends BaseHandler implements ModelHandler
{
    use WithModelInstance;

    /**
     * 将数据格式化为文本
     *
     * @param array $payload
     * @param string|null $format
     * @param array $context 格式化时的额外上下文，可以通过这个传递如业务场景等信息，采取不同的格式化逻辑
     * @return string
     */
    abstract public function formatPayloadToText(array $payload, ?string $format = null, array $context = []): string;

    /**
     * 将数据格式化为视图数据
     *
     * @param array $payload
     * @param string|null $format
     * @param array $context 格式化时的额外上下文，可以通过这个传递如业务场景等信息，采取不同的格式化逻辑
     * @return array|null
     */
    abstract public function formatPayloadToViewData(array $payload, ?string $format = null, array $context = []): ?array;
}
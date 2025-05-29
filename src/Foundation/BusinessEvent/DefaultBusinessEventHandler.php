<?php

namespace Dybasedev\LunaPrototype\Foundation\BusinessEvent;

use Dybasedev\LunaPrototype\Foundation\BusinessEvent\Models\BusinessEvent;
use Illuminate\Database\Eloquent\Model;

class DefaultBusinessEventHandler extends BusinessEventHandler
{
    /**
     * @var BusinessEvent|Model|null
     */
    protected(set) Model|BusinessEvent|null $modelInstance = null;

    /**
     * @var string
     */
    public static string $displayName = ':default_business_event_display_name';

    /**
     * @var string
     */
    public static string $description = ':default_business_event_description';

    public function handlerName(): string
    {
        return '标准业务事件处理器';
    }

    public function handlerDescription(): string
    {
        return '标准业务事件处理器，对消息有基础的格式化功能';
    }

    public function formatPayloadToText(array $payload, ?string $format = null, array $context = []): string
    {
        if ($this->modelInstance?->formatter) {
            // 默认格式化逻辑
            $replaces = [
                ...$payload,
            ];

            return str_replace(
                array_map(fn($key) => sprintf('{{%s}}', $key), array_keys($replaces)),
                array_values($replaces),
                $this->modelInstance->formatter
            );
        }

        return $this->modelInstance?->display_name ?: $this->modelInstance->name;
    }

    public function formatPayloadToViewData(array $payload, ?string $format = null, array $context = []): ?array
    {
        // 默认格式化 handler 不支持，可继承实现
        return null;
    }

}

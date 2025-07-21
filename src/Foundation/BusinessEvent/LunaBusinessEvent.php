<?php

namespace Dybasedev\LunaPrototype\Foundation\BusinessEvent;

use Dybasedev\LunaPrototype\Foundation\BusinessEvent\Models\BusinessEvent;
use Dybasedev\LunaPrototype\Foundation\Configuration\Repository;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\LunaModule;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * 业务事件管理类
 * 
 * 负责管理和维护系统中的业务事件，提供以下功能：
 * - 创建和管理业务事件
 * - 根据事件和载荷生成格式化消息
 * - 管理事件分组
 * - 缓存事件信息以提高性能
 * 
 * @package Dybasedev\LunaPrototype\Foundation\BusinessEvent
 */
class LunaBusinessEvent extends LunaModule
{
    /**
     * 事件实例缓存
     * 
     * 在内存中缓存已加载的事件实例，避免重复查询。
     * 键为事件ID，值为 BusinessEvent 实例。
     * 
     * @var BusinessEvent[]
     */
    protected(set) array $events = [];

    public function __construct(
        protected LunaBusinessEventConfigure $configure,
        protected LunaHandler $handler,
        protected Cache $cache
    ) {
    }

    /**
     * 创建业务事件
     * 
     * 在数据库中创建一个新的业务事件记录。
     * 业务事件是处理器的实例化配置，用于处理特定类型的业务操作。
     * 
     * @param string $name 事件名称（唯一标识）
     * @param string|int $group 所属组名称或ID
     * @param string|int $handler 处理器名称或ID
     * @param string $formatter 格式化器类名
     * @param string|null $displayName 显示名称，默认使用 name
     * @param Repository|null $config 配置信息
     * @return BusinessEvent 创建的业务事件实例
     * @throws RuntimeException 当保存失败时
     */
    public function createBusinessEvent(
        string $name,
        string|int $group,
        string|int $handler,
        string $formatter,
        ?string $displayName,
        ?Repository $config = null,
    ): BusinessEvent {
        $instance = new ($this->configure->model)();
        $instance->forceFill([
            'name' => $name,
            'display_name' => $displayName ?? $name,
            'group_id' => is_string($group) ? hash_code($group) : $group,
            'handler_id' => is_string($handler) ? hash_code($handler) : $handler,
            'formatter' => $formatter,
            'config' => $config ? $config->all() : [],
        ]);

        if (!$instance->save()) {
            throw new RuntimeException('Save entity failed');
        }

        $this->cache->forget('business-event:events');

        return $instance;
    }

    /**
     * 获取所有业务事件
     * 
     * 从数据库或缓存中获取系统中所有的业务事件。
     * 结果会被永久缓存，直到有新的事件被创建。
     * 
     * @return Collection<BusinessEvent> 业务事件集合
     */
    public function getAllEvents(): Collection
    {
        return collect($this->cache->rememberForever('business-event:events', function () {
            return $this->configure->model::query()->get()->all();
        }));
    }

    /**
     * 获取事件消息
     * 
     * 根据事件ID和载荷数据生成格式化的文本消息。
     * 使用事件关联的处理器和格式化器来生成消息。
     * 
     * @param string|int $event 事件名称或ID
     * @param array $payload 事件载荷数据
     * @return string 格式化后的消息文本，事件不存在时返回空字符串
     * @throws BindingResolutionException
     */
    public function eventMessage(string|int $event, array $payload = []): string
    {
        $event = is_string($event) ? hash_code($event) : $event;

        if (!isset($this->events[$event])) {
            $instance = $this->events[$event] = $this->getAllEvents()->where('id', $event)->first();
        } else {
            $instance = $this->events[$event];
        }

        if (!$instance) {
            Log::warning('event not found', [
                'event' => $event,
                'payload' => $payload,
            ]);

            return '';
        }

        /** @var BusinessEventHandler $handler */
        $handler = $instance->handlerInstance();

        return $handler->formatPayloadToText($payload);
    }

    /**
     * 检查业务事件是否存在
     * 
     * 判断指定的业务事件是否存在。
     * 'common' 事件总是存在的，它是系统默认的通用事件。
     * 
     * @param string|int $event 事件名称或ID
     * @param string|int|null $group 可选的组名称或ID，用于过滤查询
     * @return bool 存在返回 true，否则返回 false
     */
    public function existsBusinessEvent(string|int $event, string|int|null $group = null): bool
    {
        if ($event === 'common' || $event === hash_code('common')) {
            return true;
        }

        if ($group) {
            $group = is_string($group) ? hash_code($group) : $group;

            if (!isset($this->configure->groups[$group])) {
                return false;
            }
        }

        $event = is_string($event) ? hash_code($event) : $event;

        return in_array(
            $event,
            $this->getAllEvents()
                ->when($group, function (Collection $collection, $group) {
                    return $collection->where('group_id', $group);
                })
                ->pluck('id')->values()->all()
        );
    }

    /**
     * 获取所有业务事件组
     * 
     * 返回系统中注册的所有业务事件组，包括系统默认的 'common' 组。
     * 事件组用于对事件进行分类和管理。
     * 
     * @return array 事件组数组，每个元素包含 id、name 和 display_name
     */
    public function groups(): array
    {
        $groups = $this->configure->groups;
        $groups[hash_code('common')] = [
            'name' => 'common',
            'display_name' => '公共'
        ];

        return array_map(fn($id, $group) => [
            'id' => $id,
            'name' => $group['name'],
            'display_name' => $group['display_name'] ?? $group['name'],
        ], array_keys($groups), $groups);
    }

    /**
     * 获取事件概要列表
     * 
     * 返回指定组或所有组的事件概要信息。
     * 概要信息包括事件的 ID、名称和显示名称。
     *
     * @param string|int|null $group 可选的组名称或ID，null 表示所有组
     * @return array 事件概要信息数组
     */
    public function events(string|int|null $group = null): array
    {
        if ($group) {
            $group = is_string($group) ? hash_code($group) : $group;
        }

        return $this->getAllEvents()
            ->when($group, function (Collection $collection, $group) {
                return $collection->where('group_id', $group);
            })
            ->map(function (BusinessEvent $businessEvent) {
                return [
                    'id' => $businessEvent->id,
                    'name' => $businessEvent->name,
                    'display_name' => $businessEvent->display_name,
                ];
            })
            ->values()
            ->all();
    }
}
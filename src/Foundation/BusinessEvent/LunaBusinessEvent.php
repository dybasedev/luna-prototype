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

class LunaBusinessEvent extends LunaModule
{
    /**
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
     * @param string $name
     * @param string|int $group
     * @param string|int $handler
     * @param string $formatter
     * @param string|null $displayName
     * @param Repository|null $config
     * @return BusinessEvent
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
            'config' => $config ? $config->everything() : [],
        ]);

        if (!$instance->save()) {
            throw new RuntimeException('Save entity failed');
        }

        $this->cache->forget('business-event:events');

        return $instance;
    }

    /**
     * @return Collection<BusinessEvent>
     */
    public function getAllEvents(): Collection
    {
        return collect($this->cache->rememberForever('business-event:events', function () {
            return $this->configure->model::query()->get()->all();
        }));
    }

    /**
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

    public function groups(): array
    {
        return array_map(fn($id, $group) => [
            'id' => $id,
            'name' => $group['name'],
            'display_name' => $group['display_name'] ?? $group['name'],
        ], array_keys($this->configure->groups), $this->configure->groups);
    }

    /**
     * 获取事件概要列表
     *
     * @param string|int|null $group
     * @return array
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
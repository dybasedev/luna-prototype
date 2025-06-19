<?php

namespace Dybasedev\LunaPrototype\Foundation\Handler;

use Dybasedev\LunaPrototype\Foundation\Configuration\Repository;
use Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Collection;
use RuntimeException;

class LunaHandler
{
    public function __construct(
        protected LunaHandlerConfigure $configure,
        protected Cache $cache
    ) {

    }

    public function groups(): array
    {
        return array_map(fn($id, $group) => [
            'id' => $id,
            'name' => $group['name'],
            'display_name' => $group['display_name'] ?? $group['name'],
        ], array_keys($this->configure->groups), $this->configure->groups);
    }

    public function handlers(string|int|null $group = null): array
    {
        if ($group) {
            $group = is_string($group) ? hash_code($group) : $group;
            if (!isset($this->configure->groups[$group])) {
                throw new RuntimeException('Handler group not exists');
            }

            $handlers = $this->configure->groups[$group]['handlers'];
        } else {
            $handlers = $this->configure->handlers;
        }

        return array_map(fn($handler) => [
            'display_name' => $handler->handlerName(),
            'description' => $handler->handlerDescription(),
            'handler' => $handler::class,
        ], iterator_to_array((function ($handlers) {
            foreach ($handlers as $handler) {
                yield app()->make($handler);
            }
        })($handlers)));
    }

    public function createEntityHandler(
        string|int $group,
        string $name,
        string $handler,
        ?Repository $config = null,
        ?string $displayName = null,
        ?string $description = ''
    ): Handler {
        $group = is_string($group) ? hash_code($group) : $group;

        if (!isset($this->configure->groups[$group])) {
            throw new RuntimeException('Handler group not exists');
        }

        if (!in_array($handler, $this->configure->handlers)) {
            throw new RuntimeException('Handler class not exists');
        }

        if (!$config) {
            $config = new Repository([]);
        }

        $entityInstance = new ($this->configure->model)();
        $entityInstance->forceFill([
            'group_id' => $group,
            'name' => $name,
            'handler' => $handler,
            'config' => $config->all(),
            'display_name' => $displayName ?? $name,
            'description' => $description ?? ''
        ]);

        if (!$entityInstance->save()) {
            throw new RuntimeException('Save entity failed');
        }

        $this->cache->forget(sprintf('handler:entities:%d', $group));
        $this->cache->forget('handler:entities');

        return $entityInstance;
    }

    /**
     * @param string|int $group
     * @return Handler[]
     */
    public function entityHandlers(string|int $group): array
    {
        $group = is_string($group) ? hash_code($group) : $group;

        /** @var Handler[] $entities */
        $entities = $this->cache->rememberForever(sprintf('handler:entities:%d', $group), function () use ($group) {
            return $this->configure->model::query()->where('group_id', $group)->get()->all();
        });

        return $entities;
    }

    /**
     * @return Collection
     */
    public function getAllEntityHandlers(): Collection
    {
        return collect($this->cache->rememberForever('handler:entities', function () {
            return $this->configure->model::query()->get()->all();
        }));
    }

    /**
     * @param string|int $name
     * @return Handler|null
     */
    public function entityHandler(string|int $name): ?Handler
    {
        $entities = $this->getAllEntityHandlers();

        $name = is_string($name) ? hash_code($name) : $name;

        return collect($entities)->where('id', $name)->first();
    }

    public function existsEntityHandler(string|int $name): bool
    {
        return !!$this->getAllEntityHandlers()->where('id', is_string($name) ? hash_code($name) : $name)->count();
    }
}

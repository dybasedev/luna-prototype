<?php

namespace Dybasedev\LunaPrototype\Showcase\RemoteSchema;

use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Showcase\Adapter;
use Illuminate\Http\Request;

/**
 * RemoteSchema 管理器
 * 
 * 处理 RemoteSchema 相关的业务逻辑
 */
class RemoteSchemaManager
{
    /**
     * 构造函数
     * 
     * @param RemoteSchemaRegistry $registry
     * @param Adapter $adapter
     */
    public function __construct(
        protected RemoteSchemaRegistry $registry,
        protected Adapter $adapter
    ) {
    }

    /**
     * 处理请求
     * 
     * @param string $key RemoteSchema 键
     * @param string $action 操作
     * @param Request $request
     * @return mixed
     */
    public function handleRequest(string $key, string $action, Request $request): mixed
    {
        $schema = $this->registry->get($key);

        return match ($action) {
            'fields' => $this->handleFields($schema, $request),
            'meta' => $this->handleMeta($schema, $request),
            'schema' => $this->handleSchema($schema, $request),
            default => throw LunaException::create("Unknown action: {$action}")
                ->withDisplayMessage("未知操作")
        };
    }

    /**
     * 处理字段请求
     * 
     * @param RemoteSchemaInterface $schema
     * @param Request $request
     * @return array
     */
    protected function handleFields(RemoteSchemaInterface $schema, Request $request): array
    {
        return $schema->fields($request);
    }

    /**
     * 处理元数据请求
     * 
     * @param RemoteSchemaInterface $schema
     * @param Request $request
     * @return array
     */
    protected function handleMeta(RemoteSchemaInterface $schema, Request $request): array
    {
        return $schema->meta($request);
    }

    /**
     * 处理完整结构请求
     * 
     * @param RemoteSchemaInterface $schema
     * @param Request $request
     * @return array
     */
    protected function handleSchema(RemoteSchemaInterface $schema, Request $request): array
    {
        return [
            'fields' => $schema->fields($request),
            'meta' => $schema->meta($request),
        ];
    }

    /**
     * 获取 RemoteSchema 实例
     * 
     * @param string $key
     * @return RemoteSchemaInterface
     */
    public function get(string $key): RemoteSchemaInterface
    {
        return $this->registry->get($key);
    }

    /**
     * 获取注册器
     * 
     * @return RemoteSchemaRegistry
     */
    public function registry(): RemoteSchemaRegistry
    {
        return $this->registry;
    }

}
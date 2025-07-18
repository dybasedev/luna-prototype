<?php

namespace Dybasedev\LunaPrototype\Membership\Milestone\DataProviders;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;

/**
 * 基于回调函数的数据提供者
 * 
 * 允许业务系统通过回调函数灵活定义数据获取逻辑
 */
class CallbackDataProvider implements DataProvider
{
    /**
     * @param string $name 数据提供者名称
     * @param callable $callback 数据获取回调函数
     * @param callable|null $batchCallback 批量获取回调函数
     */
    public function __construct(
        protected string $name,
        protected $callback,
        protected $batchCallback = null
    ) {
    }

    /**
     * 获取数据
     *
     * @param SessionHolder $owner 数据所有者
     * @param array $params 额外参数
     * @return mixed
     */
    public function getData(SessionHolder $owner, array $params = []): mixed
    {
        return call_user_func($this->callback, $owner, $params);
    }

    /**
     * 获取数据提供者的名称
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 批量获取多个所有者的数据
     *
     * @param array<SessionHolder> $owners 所有者数组
     * @param array $params 额外参数
     * @return array<int, mixed>
     */
    public function getBatchData(array $owners, array $params = []): array
    {
        if ($this->batchCallback !== null) {
            return call_user_func($this->batchCallback, $owners, $params);
        }

        // 如果没有批量回调，逐个调用单个获取方法
        $results = [];
        foreach ($owners as $owner) {
            $results[$owner->getOperatorId()] = $this->getData($owner, $params);
        }
        return $results;
    }
}
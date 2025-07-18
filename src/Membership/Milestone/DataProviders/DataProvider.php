<?php

namespace Dybasedev\LunaPrototype\Membership\Milestone\DataProviders;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;

/**
 * 里程碑数据提供者接口
 * 
 * 用于从各种数据源获取里程碑判断所需的数据
 * 这个接口允许业务系统灵活地从数据库、缓存或其他服务获取数据
 */
interface DataProvider
{
    /**
     * 获取数据
     *
     * @param SessionHolder $owner 数据所有者
     * @param array $params 额外参数
     * @return mixed 返回获取到的数据
     */
    public function getData(SessionHolder $owner, array $params = []): mixed;

    /**
     * 获取数据提供者的名称
     *
     * @return string
     */
    public function getName(): string;

    /**
     * 批量获取多个所有者的数据
     *
     * @param array<SessionHolder> $owners 所有者数组
     * @param array $params 额外参数
     * @return array<int, mixed> 返回以所有者ID为键的数据数组
     */
    public function getBatchData(array $owners, array $params = []): array;
}
<?php

namespace Dybasedev\LunaPrototype\Membership\Milestone\DataProviders;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * 常用数据提供者工厂类
 * 
 * 提供创建常见数据提供者的便捷方法
 */
class CommonDataProviders
{
    /**
     * 创建用户消费总额数据提供者
     *
     * @param string $table 订单表名
     * @param string $amountColumn 金额列名
     * @param array $conditions 额外条件
     * @return QueryDataProvider
     */
    public static function userTotalConsumption(
        string $table = 'orders',
        string $amountColumn = 'amount',
        array $conditions = []
    ): QueryDataProvider {
        return new QueryDataProvider(
            'user_total_consumption',
            $table,
            'sum',
            $amountColumn,
            array_merge(['status' => 'completed'], $conditions),
            'user_id',
            'user_type'
        );
    }

    /**
     * 创建用户订单数量数据提供者
     *
     * @param string $table 订单表名
     * @param array $conditions 额外条件
     * @return QueryDataProvider
     */
    public static function userOrderCount(
        string $table = 'orders',
        array $conditions = []
    ): QueryDataProvider {
        return new QueryDataProvider(
            'user_order_count',
            $table,
            'count',
            'id',
            array_merge(['status' => 'completed'], $conditions),
            'user_id',
            'user_type'
        );
    }

    /**
     * 创建团队总消费数据提供者
     *
     * @param callable $teamMembersResolver 团队成员解析器，接收 SessionHolder 返回成员ID数组
     * @param string $table 订单表名
     * @param string $amountColumn 金额列名
     * @return CallbackDataProvider
     */
    public static function teamTotalConsumption(
        callable $teamMembersResolver,
        string $table = 'orders',
        string $amountColumn = 'amount'
    ): CallbackDataProvider {
        return new CallbackDataProvider(
            'team_total_consumption',
            function (SessionHolder $owner, array $params) use ($teamMembersResolver, $table, $amountColumn) {
                $memberIds = $teamMembersResolver($owner, $params);
                
                if (empty($memberIds)) {
                    return 0;
                }
                
                return DB::table($table)
                    ->whereIn('user_id', $memberIds)
                    ->where('status', 'completed')
                    ->sum($amountColumn) ?? 0;
            }
        );
    }

    /**
     * 创建用户积分数据提供者
     *
     * @param string $table 积分表名
     * @param string $pointsColumn 积分列名
     * @return QueryDataProvider
     */
    public static function userPoints(
        string $table = 'user_points',
        string $pointsColumn = 'points'
    ): QueryDataProvider {
        return new QueryDataProvider(
            'user_points',
            $table,
            'sum',
            $pointsColumn,
            [],
            'user_id',
            'user_type'
        );
    }

    /**
     * 创建用户注册天数数据提供者
     *
     * @param string $userModel 用户模型类名
     * @param string $createdAtColumn 创建时间列名
     * @return CallbackDataProvider
     */
    public static function userRegistrationDays(
        string $userModel = 'App\\Models\\User',
        string $createdAtColumn = 'created_at'
    ): CallbackDataProvider {
        return new CallbackDataProvider(
            'user_registration_days',
            function (SessionHolder $owner, array $params) use ($userModel, $createdAtColumn) {
                $user = $userModel::query()->find($owner->getOperatorId());
                
                if (!$user) {
                    return 0;
                }
                
                $createdAt = $user->getAttribute($createdAtColumn);
                if (!$createdAt) {
                    return 0;
                }
                
                return now()->diffInDays($createdAt);
            }
        );
    }

    /**
     * 创建月度消费数据提供者
     *
     * @param string $table 订单表名
     * @param string $amountColumn 金额列名
     * @param int $months 月数（默认当月）
     * @return QueryDataProvider
     */
    public static function monthlyConsumption(
        string $table = 'orders',
        string $amountColumn = 'amount',
        int $months = 1
    ): QueryDataProvider {
        $startDate = now()->subMonths($months - 1)->startOfMonth();
        $endDate = now()->endOfMonth();
        
        return new QueryDataProvider(
            'monthly_consumption',
            $table,
            'sum',
            $amountColumn,
            [
                'status' => 'completed',
                'created_at' => ['>=', $startDate],
                'created_at' => ['<=', $endDate],
            ],
            'user_id',
            'user_type'
        );
    }

    /**
     * 创建基于缓存的数据提供者
     *
     * @param string $name 数据提供者名称
     * @param callable $dataResolver 数据解析器
     * @param int $ttl 缓存时间（秒）
     * @return CallbackDataProvider
     */
    public static function cached(
        string $name,
        callable $dataResolver,
        int $ttl = 3600
    ): CallbackDataProvider {
        return new CallbackDataProvider(
            $name,
            function (SessionHolder $owner, array $params) use ($dataResolver, $ttl, $name) {
                $cacheKey = "milestone_data:{$name}:{$owner->getOperatorType()}:{$owner->getOperatorId()}";
                
                return Cache::remember($cacheKey, $ttl, function () use ($owner, $params, $dataResolver) {
                    return $dataResolver($owner, $params);
                });
            }
        );
    }

    /**
     * 创建组合数据提供者
     *
     * @param string $name 数据提供者名称
     * @param array<DataProvider> $providers 数据提供者数组
     * @param callable $combiner 组合函数，接收所有数据提供者的结果数组
     * @return CallbackDataProvider
     */
    public static function combined(
        string $name,
        array $providers,
        callable $combiner
    ): CallbackDataProvider {
        return new CallbackDataProvider(
            $name,
            function (SessionHolder $owner, array $params) use ($providers, $combiner) {
                $results = [];
                
                foreach ($providers as $key => $provider) {
                    $results[$key] = $provider->getData($owner, $params);
                }
                
                return $combiner($results);
            }
        );
    }
}
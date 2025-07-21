<?php

namespace Dybasedev\LunaPrototype\HoldingObject\Examples;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\HoldingObject\HoldingStatus;

/**
 * 参数构造器综合使用示例
 */
class ParamsUsageExample
{
    /**
     * 会员权益管理示例
     */
    public function membershipBenefits($user): array
    {
        $owner = SessionHolder::fromModel($user);
        
        // 1. 检查会员状态
        $membershipParams = unique_holding_params()
            ->owner($owner)
            ->object('membership', 'vip')
            ->forceNoCache();
            
        $membership = luna_holding_object()->getWithParams($membershipParams);
        
        if (!$membership || !HoldingStatus::from($membership->status)->isActive()) {
            return ['error' => '您还不是VIP会员'];
        }
        
        // 2. 查询会员拥有的所有权益
        $benefitsQuery = holding_query()
            ->owner($owner)
            ->object('member-benefit')
            ->normal()
            ->minQuantity(1)
            ->orderBy('created_at', 'asc');
            
        $benefits = luna_holding_object()->queryWithParams($benefitsQuery)
            ->with('benefit_details')
            ->get();
        
        // 3. 批量领取本月权益
        $monthlyBenefits = [
            ['name' => 'lottery-chance', 'id' => date('Y-m'), 'quantity' => 10],
            ['name' => 'coupon', 'id' => 'VIP_MONTHLY_' . date('Ym')],
            ['name' => 'points', 'id' => 'monthly_bonus', 'quantity' => 500],
        ];
        
        $claimResults = holding_batch()
            ->forCreate()
            ->defaultPayload([
                'claimed_at' => now(),
                'membership_level' => $membership->payload['level'] ?? 'standard',
            ])
            ->defaultEvent('holding.claim_monthly_benefits')
            ->useTransaction()
            ->addItemsForOwner($owner, $monthlyBenefits)
            ->execute();
        
        return [
            'membership' => $membership,
            'existing_benefits' => $benefits,
            'claimed_benefits' => $claimResults,
        ];
    }
    
    /**
     * 活动报名与管理示例
     */
    public function eventManagement($eventId): array
    {
        // 1. 查询活动的所有报名者
        $participantsQuery = holding_query()
            ->object('event-registration', $eventId)
            ->normal()
            ->latest()
            ->limit(100);
            
        $participants = luna_holding_object()->queryWithParams($participantsQuery)
            ->with(['owner' => function ($q) {
                $q->select('id', 'name', 'email', 'phone');
            }])
            ->get();
        
        // 2. 统计报名人数
        $totalCount = luna_holding_object()->countWithParams(
            holding_query()->object('event-registration', $eventId)
        );
        
        // 3. 批量确认参与者
        $confirmedUsers = $participants->filter(function ($p) {
            return $p->payload['payment_status'] ?? false === 'paid';
        });
        
        $confirmBatch = holding_batch()
            ->forUpdateStatus(HoldingStatus::Used)
            ->defaultPayload(['confirmed_at' => now()])
            ->defaultEvent('holding.event_confirm')
            ->continueOnError();
            
        foreach ($confirmedUsers as $participant) {
            $confirmBatch->addItem(
                SessionHolder::fromArray([
                    'id' => $participant->owner_id,
                    'type' => $participant->owner_type,
                ]),
                'event-registration',
                $eventId
            );
        }
        
        $confirmResults = $confirmBatch->execute();
        
        return [
            'total_registrations' => $totalCount,
            'participants' => $participants,
            'confirmed_count' => collect($confirmResults)->where('success', true)->count(),
        ];
    }
    
    /**
     * 积分商城兑换示例
     */
    public function pointsExchange($user, $itemId, $quantity = 1): array
    {
        $owner = SessionHolder::fromModel($user);
        
        // 1. 获取商品信息（假设从其他地方获取）
        $item = $this->getExchangeItem($itemId);
        $requiredPoints = $item['points'] * $quantity;
        
        // 2. 检查用户积分
        $pointsParams = unique_holding_params()
            ->owner($owner)
            ->object('points', 'balance');
            
        $userPoints = luna_holding_object()->getWithParams($pointsParams);
        
        if (!$userPoints || $userPoints->quantity < $requiredPoints) {
            return [
                'success' => false,
                'message' => sprintf('积分不足，需要 %d 积分，您当前有 %d 积分', 
                    $requiredPoints, 
                    $userPoints->quantity ?? 0
                ),
            ];
        }
        
        // 3. 执行兑换（使用批量操作确保原子性）
        $exchangeId = uniqid('EX');
        
        $results = holding_batch()
            ->useTransaction()
            ->continueOnError(false)
            // 扣减积分
            ->addItem(
                $owner,
                'points',
                'balance',
                ['exchange_id' => $exchangeId, 'item' => $item['name']],
                $requiredPoints,
                null,
                'holding.points_exchange'
            )
            // 添加兑换记录
            ->addItem(
                $owner,
                'exchange-history',
                $exchangeId,
                [
                    'item_id' => $itemId,
                    'item_name' => $item['name'],
                    'quantity' => $quantity,
                    'points_used' => $requiredPoints,
                ],
                $quantity,
                null,
                'holding.exchange_record'
            )
            // 如果是虚拟商品，直接发放
            ->addItem(
                $owner,
                $item['type'],
                $item['reward_id'] ?? $itemId,
                ['source' => 'points_exchange', 'exchange_id' => $exchangeId],
                $item['reward_quantity'] ?? 1,
                null,
                'holding.exchange_reward'
            )
            ->forCreate() // 积分使用 decrease，其他使用 create
            ->execute();
        
        // 特殊处理积分扣减
        if ($results[0]['success']) {
            luna_holding_object()->decreaseUniqueHoldingQuantity(
                $owner,
                'points',
                'balance',
                $requiredPoints,
                ['exchange_id' => $exchangeId],
                'holding.points_use'
            );
        }
        
        return [
            'success' => collect($results)->every('success'),
            'exchange_id' => $exchangeId,
            'results' => $results,
        ];
    }
    
    /**
     * 数据分析示例
     */
    public function analyticsExample(): array
    {
        // 1. 今日签到统计
        $todayCheckIns = luna_holding_object()->countWithParams(
            holding_query()
                ->object('daily-checkin', 1)
                ->normal()
        );
        
        // 2. 本月活跃用户（有任何持有记录变动）
        $monthlyActiveQuery = luna_holding_object()
            ->queryWithParams(holding_query()->latest())
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->distinct('owner_id', 'owner_type');
            
        $monthlyActiveUsers = $monthlyActiveQuery->count();
        
        // 3. 热门兑换商品 TOP 10
        $popularExchanges = luna_holding_object()
            ->queryWithParams(
                holding_query()
                    ->object('exchange-history')
                    ->latest()
                    ->limit(1000)
            )
            ->selectRaw('payload->>"$.item_id" as item_id, COUNT(*) as count')
            ->groupBy('item_id')
            ->orderByDesc('count')
            ->limit(10)
            ->get();
        
        // 4. 用户持有对象分布
        $holdingDistribution = luna_holding_object()
            ->queryWithParams(holding_query())
            ->selectRaw('object_type, COUNT(DISTINCT CONCAT(owner_type, ":", owner_id)) as holder_count')
            ->groupBy('object_type')
            ->get()
            ->mapWithKeys(function ($item) {
                $objectName = $this->getObjectNameByType($item->object_type);
                return [$objectName => $item->holder_count];
            });
        
        return [
            'today_checkins' => $todayCheckIns,
            'monthly_active_users' => $monthlyActiveUsers,
            'popular_exchanges' => $popularExchanges,
            'holding_distribution' => $holdingDistribution,
        ];
    }
    
    /**
     * 模拟获取兑换商品信息
     */
    private function getExchangeItem($itemId): array
    {
        // 实际项目中从数据库或配置获取
        return [
            'id' => $itemId,
            'name' => '优惠券',
            'points' => 100,
            'type' => 'coupon',
            'reward_id' => 'POINTS_REWARD_10',
            'reward_quantity' => 1,
        ];
    }
    
    /**
     * 模拟获取对象类型名称
     */
    private function getObjectNameByType($type): string
    {
        // 实际项目中应该有映射关系
        $mapping = [
            hash_code('daily-checkin') => '每日签到',
            hash_code('lottery-chance') => '抽奖机会',
            hash_code('coupon') => '优惠券',
            hash_code('points') => '积分',
        ];
        
        return $mapping[$type] ?? "未知类型({$type})";
    }
}
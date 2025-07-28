<?php

namespace Examples\HoldingObject;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\HoldingObject\HoldingStatus;
use Dybasedev\LunaPrototype\HoldingObject\UniqueHoldingParams;

/**
 * UniqueHoldingParams 参数构造器使用示例
 */
class ParamsBuilderExample
{
    /**
     * 签到功能示例（使用参数构造器）
     */
    public function checkInWithParams($user): array
    {
        $owner = SessionHolder::fromModel($user);
        
        try {
            // 构造参数检查是否已签到
            $params = unique_holding_params()
                ->owner($owner)
                ->object('daily-checkin', 1);
                
            if (luna_holding_object()->existsWithParams($params)) {
                return [
                    'success' => false,
                    'message' => '今日已签到',
                ];
            }
            
            // 构造参数执行签到
            $params = unique_holding_params()
                ->owner($owner)
                ->object('daily-checkin', 1)
                ->payload([
                    'check_in_time' => now()->toDateTimeString(),
                    'check_in_date' => date('Y-m-d'),
                    'ip' => request()->ip(),
                ])
                ->event('holding.daily_checkin');
                
            $holding = luna_holding_object()->createWithParams($params);
            
            return [
                'success' => true,
                'message' => '签到成功',
                'data' => [
                    'check_in_time' => $holding->payload['check_in_time'],
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * 抽奖次数管理示例（使用参数构造器）
     */
    public function manageLotteryChances($user, int $lotteryId): array
    {
        $owner = SessionHolder::fromModel($user);
        
        // 增加抽奖次数 - 方式1：使用参数构造器配合新方法
        $params = unique_holding_params()
            ->owner($owner)
            ->object('lottery-chance', $lotteryId)
            ->quantity(1)
            ->with('source', 'daily_login')
            ->with('grant_time', now()->toDateTimeString())
            ->event('holding.grant_chance.daily_login');
            
        $granted = luna_holding_object()->increaseWithParams($params);
        
        // 增加抽奖次数 - 方式2：使用参数构造器的 build 方法配合原方法
        $params2 = unique_holding_params()
            ->owner($owner)
            ->object('lottery-chance', $lotteryId)
            ->quantity(2)
            ->with('source', 'share')
            ->event('holding.grant_chance.share');
            
        $granted2 = luna_holding_object()->increaseUniqueHoldingQuantity(
            ...$params2->buildForQuantityChange()
        );
        
        // 使用抽奖次数
        try {
            $params = unique_holding_params()
                ->owner($owner)
                ->object('lottery-chance', $lotteryId)
                ->quantity(1)
                ->with('used_at', now()->toDateTimeString())
                ->event('holding.use_chance');
                
            $used = luna_holding_object()->decreaseWithParams($params);
                
            if (!$used) {
                return [
                    'success' => false,
                    'message' => '抽奖次数不足',
                ];
            }
            
            // 执行抽奖逻辑...
            $prize = $this->drawLottery();
            
            return [
                'success' => true,
                'message' => '抽奖成功',
                'data' => [
                    'prize' => $prize,
                    'remaining_chances' => $used->quantity,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '抽奖失败：' . $e->getMessage(),
            ];
        }
    }
    
    /**
     * 优惠券状态管理示例
     */
    public function updateCouponStatus($user, string $couponCode, string $orderId): array
    {
        $owner = SessionHolder::fromModel($user);
        
        // 获取优惠券（强制不使用缓存）
        $params = unique_holding_params()
            ->owner($owner)
            ->object('coupon', $couponCode)
            ->forceNoCache();
            
        $coupon = luna_holding_object()->getWithParams($params);
        
        if (!$coupon) {
            return [
                'success' => false,
                'message' => '优惠券不存在',
            ];
        }
        
        // 更新为已使用状态
        $params = unique_holding_params()
            ->owner($owner)
            ->object('coupon', $couponCode)
            ->status(HoldingStatus::Used)
            ->with('used_at', now()->toDateTimeString())
            ->with('order_id', $orderId)
            ->event('holding.use_coupon');
            
        $updated = luna_holding_object()->updateStatusWithParams($params);
        
        return [
            'success' => true,
            'message' => '优惠券使用成功',
            'data' => [
                'coupon_code' => $couponCode,
                'order_id' => $orderId,
            ],
        ];
    }
    
    /**
     * 直接使用参数对象的示例
     */
    public function directParamsUsage($user): void
    {
        $owner = SessionHolder::fromModel($user);
        
        // 创建一个可复用的参数对象
        $baseParams = unique_holding_params()
            ->owner($owner)
            ->object('feature-access', 'premium')
            ->event('holding.feature');
        
        // 基于基础参数创建不同的操作
        
        // 检查是否有权限
        if (!luna_holding_object()->existsWithParams($baseParams)) {
            // 授予权限
            $grantParams = clone $baseParams;
            $grantParams->payload([
                'granted_at' => now()->toDateTimeString(),
                'granted_by' => 'system',
            ]);
            
            luna_holding_object()->createWithParams($grantParams);
        }
        
        // 更新权限状态
        $updateParams = clone $baseParams;
        $updateParams->status(HoldingStatus::Frozen)
            ->with('frozen_reason', 'payment_overdue')
            ->with('frozen_at', now()->toDateTimeString());
            
        luna_holding_object()->updateStatusWithParams($updateParams);
    }
    
    /**
     * 批量操作示例
     */
    public function batchOperationsWithParams($users, string $eventName): array
    {
        $results = [];
        
        foreach ($users as $user) {
            $params = unique_holding_params()
                ->owner(SessionHolder::fromModel($user))
                ->object('event-participation', $eventName)
                ->payload([
                    'participated_at' => now()->toDateTimeString(),
                    'source' => 'batch_invite',
                ])
                ->event('holding.event_participation');
                
            try {
                $holding = luna_holding_object()->createWithParams($params);
                $results[$user->id] = [
                    'success' => true,
                    'holding_id' => $holding->id,
                ];
            } catch (\Exception $e) {
                $results[$user->id] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * 模拟抽奖
     */
    private function drawLottery(): string
    {
        $prizes = [
            '一等奖' => 1,
            '二等奖' => 5,
            '三等奖' => 10,
            '参与奖' => 84,
        ];
        
        $random = rand(1, 100);
        $cumulative = 0;
        
        foreach ($prizes as $prize => $probability) {
            $cumulative += $probability;
            if ($random <= $cumulative) {
                return $prize;
            }
        }
        
        return '参与奖';
    }
}
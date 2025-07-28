<?php

namespace Examples\HoldingObject;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\HoldingObject\HoldingStatus;
use Illuminate\Support\Facades\DB;

/**
 * HoldingObject 组件使用示例
 */
class SimpleExample
{
    /**
     * 签到功能示例
     */
    public function checkInExample($user): array
    {
        $owner = SessionHolder::fromModel($user);
        try {
            // 执行签到
            $holding = luna_holding_object()->createUniqueHolding(
                $owner,
                'daily-checkin',
                1,
                [
                    'check_in_time' => now()->toDateTimeString(),
                    'check_in_date' => date('Y-m-d'),
                    'ip' => request()->ip(),
                    'device' => request()->header('User-Agent'),
                ],
                1.0,
                null,
                'holding.daily_checkin'
            );
            
            // 计算连续签到天数
            $consecutiveDays = $this->calculateConsecutiveDays($owner);
            
            return [
                'success' => true,
                'message' => '签到成功',
                'data' => [
                    'consecutive_days' => $consecutiveDays,
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
     * 商品限购示例
     */
    public function purchaseLimitExample($user, int $productId, int $quantity): array
    {
        $owner = SessionHolder::fromModel($user);
        
        try {
            // 创建或更新限购记录
            $holding = luna_holding_object()->createUniqueHolding(
                $owner,
                'product-limit',
                $productId,
                [
                    'order_id' => uniqid('ORDER'),
                    'quantity' => $quantity,
                    'purchase_time' => now()->toDateTimeString(),
                ],
                $quantity,
                null,
                'holding.purchase_limit'
            );
            
            return [
                'success' => true,
                'message' => '购买成功',
                'data' => [
                    'total_purchased' => $holding->quantity,
                    'product_id' => $productId,
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
     * 抽奖次数管理示例
     */
    public function lotteryChanceExample($user, int $lotteryId, string $source): array
    {
        $owner = SessionHolder::fromModel($user);
        
        try {
            // 根据不同来源给予不同的抽奖次数
            $chances = match ($source) {
                'daily_login' => 1,
                'share' => 2,
                'purchase' => 3,
                'invite_friend' => 5,
                default => 1,
            };
            
            // 增加抽奖次数
            $holding = luna_holding_object()->createUniqueHolding(
                $owner,
                'lottery-chance',
                $lotteryId,
                [
                    'source' => $source,
                    'grant_time' => now()->toDateTimeString(),
                ],
                $chances,
                null,
                'holding.grant_chance.' . $source
            );
            
            return [
                'success' => true,
                'message' => sprintf('成功获得%d次抽奖机会', $chances),
                'data' => [
                    'total_chances' => $holding->quantity,
                    'new_chances' => $chances,
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
     * 使用抽奖次数示例
     */
    public function useLotteryChance($user, int $lotteryId): array
    {
        $owner = SessionHolder::fromModel($user);
        
        return DB::transaction(function () use ($owner, $lotteryId) {
            // 获取当前抽奖次数
            $holding = luna_holding_object()->getUniqueHolding($owner, 'lottery-chance', $lotteryId);
            
            if (!$holding || $holding->quantity < 1) {
                return [
                    'success' => false,
                    'message' => '抽奖次数不足',
                ];
            }
            
            // 减少抽奖次数
            $holding->quantity -= 1;
            $holding->save();
            
            // 执行抽奖逻辑（这里简化处理）
            $prize = $this->drawLottery();
            
            return [
                'success' => true,
                'message' => '抽奖成功',
                'data' => [
                    'prize' => $prize,
                    'remaining_chances' => $holding->quantity,
                ],
            ];
        });
    }
    
    /**
     * 查询持有记录示例
     */
    public function queryHoldingsExample($user): array
    {
        $owner = SessionHolder::fromModel($user);
        
        // 获取所有正常状态的持有记录
        $normalHoldings = luna_holding_object()->getOwnerHoldings($owner, [
            'status' => HoldingStatus::Normal->value,
        ]);
        
        // 获取特定类型的持有记录
        $checkInRecords = luna_holding_object()
            ->queryUniqueHoldings($owner, 'daily-checkin')
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get();
        
        // 统计本月签到次数
        $monthlyCheckIns = luna_holding_object()
            ->queryUniqueHoldings($owner, 'daily-checkin')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();
        
        return [
            'all_holdings' => $normalHoldings->toArray(),
            'recent_checkins' => $checkInRecords->toArray(),
            'monthly_checkin_count' => $monthlyCheckIns,
        ];
    }
    
    /**
     * 计算连续签到天数
     */
    private function calculateConsecutiveDays(SessionHolder $owner): int
    {
        // 获取最近30天的签到记录
        $checkIns = luna_holding_object()
            ->queryUniqueHoldings($owner, 'daily-checkin')
            ->where('status', HoldingStatus::Normal->value)
            ->where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at', 'desc')
            ->get()
            ->pluck('payload.check_in_date')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
        
        if (empty($checkIns)) {
            return 0;
        }
        
        // 从今天开始往前检查连续性
        $consecutive = 0;
        $currentDate = new \DateTime();
        
        foreach ($checkIns as $checkInDate) {
            $checkIn = new \DateTime($checkInDate);
            $dayDiff = $currentDate->diff($checkIn)->days;
            
            if ($dayDiff === $consecutive) {
                $consecutive++;
            } else {
                break;
            }
        }
        
        return $consecutive;
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
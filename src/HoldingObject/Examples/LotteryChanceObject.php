<?php

namespace Dybasedev\LunaPrototype\HoldingObject\Examples;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\HoldingObject\HoldingStatus;
use Dybasedev\LunaPrototype\HoldingObject\UniqueObject;

/**
 * 抽奖机会对象示例
 * 
 * 用于管理用户的抽奖次数，支持通过各种方式获得和消耗抽奖机会
 */
class LotteryChanceObject extends UniqueObject
{
    protected(set) ?string $name = 'lottery-chance';
    
    /**
     * 允许多次持有（累计抽奖次数）
     */
    protected(set) bool $enableHoldMultiple = true;
    
    public function __construct()
    {
        $this->conflictMessage = '操作失败，请稍后重试';
    }
    
    /**
     * 格式化对象ID（活动ID）
     *
     * @param string|int $id
     * @return string|int
     */
    public function reformatId(string|int $id): string|int
    {
        return (int) $id;
    }
    
    /**
     * 权限检查
     *
     * @param SessionHolder $owner
     * @param string|int $objectId
     * @param array $payload
     * @return bool
     */
    public function permit(SessionHolder $owner, string|int $objectId, array $payload = []): bool
    {
        // 检查活动是否存在且进行中
        // $lottery = Lottery::find($objectId);
        // if (!$lottery || !$lottery->is_active) {
        //     $this->conflictMessage = '活动不存在或已结束';
        //     return false;
        // }
        
        // 检查来源是否合法
        $validSources = [
            'daily_login',      // 每日登录
            'share',           // 分享活动
            'purchase',        // 购买商品
            'invite_friend',   // 邀请好友
            'complete_task',   // 完成任务
            'system_grant',    // 系统赠送
        ];
        
        $source = $payload['source'] ?? '';
        if (!in_array($source, $validSources)) {
            $this->conflictMessage = '无效的获取来源';
            return false;
        }
        
        // 根据不同来源进行额外检查
        switch ($source) {
            case 'daily_login':
                // 检查今日是否已通过登录获得
                return $this->checkDailyLoginSource($owner, $objectId);
                
            case 'share':
                // 检查分享次数限制
                return $this->checkShareSource($owner, $objectId, $payload);
                
            case 'invite_friend':
                // 检查邀请的好友是否有效
                return $this->checkInviteSource($owner, $objectId, $payload);
                
            default:
                return true;
        }
    }
    
    /**
     * 验证载荷数据
     *
     * @param array $payload
     * @return bool
     */
    public function validatePayload(array $payload): bool
    {
        // 必须包含来源
        if (!isset($payload['source'])) {
            return false;
        }
        
        // 根据来源验证额外数据
        switch ($payload['source']) {
            case 'purchase':
                // 购买必须包含订单ID
                return isset($payload['order_id']);
                
            case 'invite_friend':
                // 邀请必须包含被邀请人ID
                return isset($payload['invited_user_id']);
                
            case 'share':
                // 分享必须包含分享渠道
                return isset($payload['share_channel']);
                
            default:
                return true;
        }
    }
    
    /**
     * 创建持有记录后的回调
     *
     * @param $holding
     * @return void
     */
    public function createdHolding($holding): void
    {
        // 记录获得抽奖机会的日志
        $payload = $holding->payload;
        $sourceText = $this->getSourceText($payload['source']);
        
        // 可以触发通知
        // notification(new LotteryChanceReceived($holding->owner, $holding->quantity, $sourceText));
    }
    
    /**
     * 检查每日登录来源
     */
    protected function checkDailyLoginSource(SessionHolder $owner, int $lotteryId): bool
    {
        // 检查今日是否已通过登录获得抽奖机会
        $today = date('Y-m-d');
        // 在实际使用中，这里应该注入 LunaHoldingObject 实例
        // $existingLogs = luna_holding_object()
        //     ->queryUniqueHoldings($owner, $this->name)
        //     ->where('object_id', $lotteryId)
        //     ->whereDate('created_at', $today)
        //     ->whereJsonContains('payload->source', 'daily_login')
        //     ->exists();
        
        // 为了示例，这里简化处理
        $existingLogs = false;
        
        if ($existingLogs) {
            $this->conflictMessage = '今日登录奖励已领取';
            return false;
        }
        
        return true;
    }
    
    /**
     * 检查分享来源
     */
    protected function checkShareSource(SessionHolder $owner, int $lotteryId, array $payload): bool
    {
        // 每日分享次数限制
        $dailyShareLimit = 3;
        
        $today = date('Y-m-d');
        // 在实际使用中，这里应该注入 LunaHoldingObject 实例
        // $shareCount = luna_holding_object()
        //     ->queryUniqueHoldings($owner, $this->name)
        //     ->where('object_id', $lotteryId)
        //     ->whereDate('created_at', $today)
        //     ->whereJsonContains('payload->source', 'share')
        //     ->count();
        
        // 为了示例，这里简化处理
        $shareCount = 0;
        
        if ($shareCount >= $dailyShareLimit) {
            $this->conflictMessage = sprintf('每日最多可通过分享获得%d次抽奖机会', $dailyShareLimit);
            return false;
        }
        
        return true;
    }
    
    /**
     * 检查邀请来源
     */
    protected function checkInviteSource(SessionHolder $owner, int $lotteryId, array $payload): bool
    {
        if (!isset($payload['invited_user_id'])) {
            return false;
        }
        
        // 检查是否已经为该用户发放过奖励
        // 在实际使用中，这里应该注入 LunaHoldingObject 实例
        // $existingReward = luna_holding_object()
        //     ->queryUniqueHoldings($owner, $this->name)
        //     ->where('object_id', $lotteryId)
        //     ->whereJsonContains('payload->source', 'invite_friend')
        //     ->whereJsonContains('payload->invited_user_id', $payload['invited_user_id'])
        //     ->exists();
        
        // 为了示例，这里简化处理
        $existingReward = false;
        
        if ($existingReward) {
            $this->conflictMessage = '已为该好友发放过邀请奖励';
            return false;
        }
        
        return true;
    }
    
    /**
     * 获取来源文本描述
     */
    protected function getSourceText(string $source): string
    {
        $sourceTexts = [
            'daily_login' => '每日登录',
            'share' => '分享活动',
            'purchase' => '购买商品',
            'invite_friend' => '邀请好友',
            'complete_task' => '完成任务',
            'system_grant' => '系统赠送',
        ];
        
        return $sourceTexts[$source] ?? '其他';
    }
}
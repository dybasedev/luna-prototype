<?php

namespace Examples\HoldingObject;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\HoldingObject\UniqueObject;

/**
 * 功能访问权限对象示例
 * 
 * 用于管理用户对特定功能的访问权限，例如试用功能、Beta功能等。
 * 使用 hash_code 函数将功能名称转换为数字ID。
 */
class FeatureAccessObject extends UniqueObject
{
    protected(set) ?string $name = 'feature-access';
    
    /**
     * 不允许多次持有（每个功能只需要一个访问记录）
     */
    protected(set) bool $enableHoldMultiple = false;
    
    public function __construct()
    {
        $this->conflictMessage = '您已经拥有该功能的访问权限';
    }
    
    /**
     * 格式化对象ID
     * 使用 hash_code 将功能名称转换为数字ID
     *
     * @param string|int $id
     * @return string|int
     */
    public function reformatId(string|int $id): string|int
    {
        // 如果是字符串（功能名称），使用 hash_code 转换
        if (is_string($id)) {
            return hash_code($id);
        }
        
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
        // 检查功能是否需要特定条件
        if (isset($payload['feature_name'])) {
            $featureName = $payload['feature_name'];
            
            // 示例：某些功能需要 VIP 才能访问
            $vipOnlyFeatures = ['advanced-analytics', 'ai-assistant', 'priority-support'];
            if (in_array($featureName, $vipOnlyFeatures)) {
                // 这里应该检查用户的 VIP 状态
                // $isVip = $this->checkUserVipStatus($owner);
                // if (!$isVip) {
                //     $this->conflictMessage = '该功能需要 VIP 会员才能使用';
                //     return false;
                // }
            }
        }
        
        return true;
    }
    
    /**
     * 验证载荷数据
     *
     * @param array $payload
     * @return bool
     */
    public function validatePayload(array $payload): bool
    {
        // 必须包含功能名称
        if (!isset($payload['feature_name'])) {
            return false;
        }
        
        // 必须包含授权时间
        if (!isset($payload['granted_at'])) {
            return false;
        }
        
        // 可选：过期时间
        // 可选：授权来源（如：试用、购买、赠送等）
        
        return true;
    }
    
    /**
     * 创建持有记录后的回调
     *
     * @param $holding
     * @return void
     */
    public function createdHolding($holding): void
    {
        // 可以在这里记录功能授权日志
        // 发送欢迎使用新功能的通知等
        
        // event(new FeatureAccessGranted($holding->owner, $holding->payload['feature_name']));
    }
}
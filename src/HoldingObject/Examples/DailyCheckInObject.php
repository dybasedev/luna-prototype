<?php

namespace Dybasedev\LunaPrototype\HoldingObject\Examples;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\HoldingObject\UniqueObject;

/**
 * 每日签到对象示例
 * 
 * 用于实现每日签到功能，每个用户每天只能签到一次。
 * 使用固定ID，配合 Schedule 组件定期清理过期数据。
 */
class DailyCheckInObject extends UniqueObject
{
    protected(set) ?string $name = 'daily-checkin';
    
    /**
     * 不允许多次持有（每日只能签到一次）
     */
    protected(set) bool $enableHoldMultiple = false;
    
    public function __construct()
    {
        $this->conflictMessage = '您今天已经签到过了';
    }
    
    /**
     * 格式化对象ID
     * 每日签到使用固定ID，通过 Schedule 组件每日清理
     *
     * @param string|int $id
     * @return string|int
     */
    public function reformatId(string|int $id): string|int
    {
        // 使用固定ID，所有用户的每日签到都使用同一个ID
        // 通过数据库唯一索引确保每个用户每天只能签到一次
        return 1;
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
        // 在测试环境中跳过时间检查
        if (app()->environment('testing')) {
            return true;
        }
        
        // 检查是否在有效的签到时间范围内
        $currentHour = (int) date('H');
        if ($currentHour < 6 || $currentHour > 23) {
            $this->conflictMessage = '签到时间为每日 6:00 - 23:00';
            return false;
        }
        
        // 可以在这里添加其他权限检查
        // 例如：检查用户状态、VIP等级等
        
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
        // 签到必须包含签到时间
        if (!isset($payload['check_in_time'])) {
            return false;
        }
        
        // 签到日期（用于记录具体是哪天签到的）
        if (!isset($payload['check_in_date'])) {
            return false;
        }
        
        // 可以添加其他验证
        // 例如：验证IP地址格式、设备信息等
        
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
        // 可以在这里触发签到成功的事件
        // 例如：发放签到奖励、更新签到统计等
        
        // 记录连续签到天数到用户扩展信息中
        // 这个逻辑应该在实际的业务代码中实现
        
        // event(new UserCheckedIn($holding->owner, $holding));
    }
}
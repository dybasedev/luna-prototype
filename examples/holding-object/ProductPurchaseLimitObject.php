<?php

namespace Examples\HoldingObject;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\HoldingObject\UniqueObject;

/**
 * 商品限购对象示例
 * 
 * 用于实现商品限购功能，限制每个用户购买特定商品的数量
 */
class ProductPurchaseLimitObject extends UniqueObject
{
    protected(set) ?string $name = 'product-purchase-limit';
    
    /**
     * 允许多次持有（用于累计购买数量）
     */
    protected(set) bool $enableHoldMultiple = true;
    
    public function __construct()
    {
        $this->conflictMessage = '您已达到该商品的购买上限';
    }
    
    /**
     * 商品购买限制配置
     * 可以从数据库或配置文件读取
     */
    protected array $purchaseLimits = [
        // 商品ID => 每人限购数量
        1001 => 5,
        1002 => 1,
        1003 => 10,
    ];
    
    /**
     * 格式化对象ID（商品ID）
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
        $productId = (int) $objectId;
        
        // 检查商品是否有限购设置
        if (!isset($this->purchaseLimits[$productId])) {
            // 没有限购设置的商品不限制购买
            return true;
        }
        
        // 获取限购数量
        $limit = $this->purchaseLimits[$productId];
        
        // 获取当前购买数量（从payload中）
        $purchaseQuantity = $payload['quantity'] ?? 1;
        
        // 获取已购买记录
        // 在实际使用中，这里应该注入 LunaHoldingObject 实例
        // $existingHolding = luna_holding_object()->getUniqueHolding($owner, $this->name, $productId);
        // 为了示例，这里简化处理
        $existingHolding = null;
        
        if ($existingHolding) {
            // 检查是否超过限购
            if (($existingHolding->quantity + $purchaseQuantity) > $limit) {
                $this->conflictMessage = sprintf(
                    '该商品每人限购%d件，您已购买%d件，本次最多可购买%d件',
                    $limit,
                    (int) $existingHolding->quantity,
                    max(0, $limit - $existingHolding->quantity)
                );
                return false;
            }
        } else {
            // 首次购买，检查数量是否超过限制
            if ($purchaseQuantity > $limit) {
                $this->conflictMessage = sprintf('该商品每人限购%d件', $limit);
                return false;
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
        // 必须包含订单ID
        if (!isset($payload['order_id'])) {
            return false;
        }
        
        // 必须包含购买数量
        if (!isset($payload['quantity']) || $payload['quantity'] <= 0) {
            return false;
        }
        
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
        // 可以在这里记录购买日志
        // 例如：更新商品销量统计、发送购买成功通知等
    }
}
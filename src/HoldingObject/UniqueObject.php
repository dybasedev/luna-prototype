<?php

namespace Dybasedev\LunaPrototype\HoldingObject;

use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Illuminate\Contracts\Container\BindingResolutionException;

/**
 * 唯一持有对象
 *
 * 通过该类可以定义一个成员仅能够唯一持有的对象，
 * 可以基于这个实现非常多的业务逻辑。
 */
abstract class UniqueObject
{
    protected(set) ?int $defaultId = 1;

    /**
     * @var bool 是否允许重复持有，若为 true 时，对同一个所有者持有唯一对象会增加数量，否则会报错
     */
    protected(set) bool $enableHoldMultiple = false;

    /**
     * @var float|null 最大持有数量限制，null 表示不限制
     */
    protected(set) ?float $maxQuantity = null;

    /**
     * @var float|null 单次增加的最大数量限制，null 表示不限制
     */
    protected(set) ?float $maxIncreaseQuantity = null;

    /**
     * @var float|null 单次减少的最大数量限制，null 表示不限制
     */
    protected(set) ?float $maxDecreaseQuantity = null;

    /**
     * @var string|null 对象名称
     */
    protected(set) ?string $name = null {
        get {
            return $this->name;
        }
    }

    final public function named(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * 验证创建唯一对象时的 ID
     *
     * @param string|int $id
     * @return bool
     */
    public function validateId(string|int $id): bool
    {
        return true;
    }

    /**
     * 重新格式化 ID
     *
     * 格式化后的 ID 是实际写入数据库的 ID
     *
     * @param string|int $id
     * @return string|int
     */
    public function reformatId(string|int $id): string|int
    {
        if (!$this->validateId($id)) {
            throw LunaException::create('Invalid ID');
        }

        return $id;
    }

    /**
     * 判断操作人是否具备该对象创建权限
     *
     * @param SessionHolder $owner
     * @param string|int $objectId
     * @param array $payload
     * @return bool
     */
    public function permit(SessionHolder $owner, string|int $objectId, array $payload = []): bool
    {
        // 继承实现具体的许可逻辑
        // 抛出异常或返回 false 表示没有权限
        return true;
    }

    /**
     * 创建唯一对象后触发
     *
     * @param $holding
     * @return void
     */
    public function createdHolding($holding): void
    {
        // 创建后的逻辑
    }

    /**
     * 创建唯一对象发生冲突时触发，并且允许修改冲突信息用以展示给客户端
     *
     * @param array|null $context
     * @return string
     */
    public function conflictMessage(?array $context = null): string
    {
        // TODO 此处消息随便定义的，后续调整一个更普遍的的文本
        return 'Unique object conflict';
    }

    /**
     * 创建唯一对象时，验证传入的 payload 是否合法，同时返回验证通过的 payload
     *
     * @param array|null $payload
     * @return array
     */
    public function payloadValidate(?array $payload = []): array
    {
        return $payload ?? [];
    }

    /**
     * 验证载荷数据
     *
     * @param array $payload
     * @return bool
     */
    public function validatePayload(array $payload): bool
    {
        return true;
    }

    /**
     * @var string|null 冲突时的提示信息
     */
    public string $conflictMessage = '';

    /**
     * 获取数量超限时的消息
     *
     * @param float $currentQuantity 当前数量
     * @param float $requestedQuantity 请求的数量
     * @param array $context 上下文
     * @return string
     */
    public function getQuantityExceededMessage(float $currentQuantity, float $requestedQuantity, array $context = []): string
    {
        if ($this->maxQuantity !== null) {
            return sprintf('数量超过限制，最多可持有 %s，当前已持有 %s', $this->maxQuantity, $currentQuantity);
        }
        return '数量超过限制';
    }

    /**
     * 获取增加数量超限时的消息
     *
     * @param float $requestedQuantity 请求增加的数量
     * @param array $context 上下文
     * @return string
     */
    public function getIncreaseExceededMessage(float $requestedQuantity, array $context = []): string
    {
        if ($this->maxIncreaseQuantity !== null) {
            return sprintf('单次增加数量不能超过 %s', $this->maxIncreaseQuantity);
        }
        return '增加数量超过限制';
    }

    /**
     * 获取减少数量超限时的消息
     *
     * @param float $requestedQuantity 请求减少的数量
     * @param array $context 上下文
     * @return string
     */
    public function getDecreaseExceededMessage(float $requestedQuantity, array $context = []): string
    {
        if ($this->maxDecreaseQuantity !== null) {
            return sprintf('单次减少数量不能超过 %s', $this->maxDecreaseQuantity);
        }
        return '减少数量超过限制';
    }

    /**
     * 获取数量不足时的消息
     *
     * @param float $currentQuantity 当前数量
     * @param float $requestedQuantity 请求减少的数量
     * @param array $context 上下文
     * @return string
     */
    public function getInsufficientQuantityMessage(float $currentQuantity, float $requestedQuantity, array $context = []): string
    {
        return sprintf('数量不足，当前数量为 %s，无法减少 %s', $currentQuantity, $requestedQuantity);
    }

    /**
     * 检查数量限制
     *
     * @param float $currentQuantity 当前数量
     * @param float $changeQuantity 变化数量（正数为增加，负数为减少）
     * @return bool
     */
    public function checkQuantityLimit(float $currentQuantity, float $changeQuantity): bool
    {
        $newQuantity = $currentQuantity + $changeQuantity;
        
        // 检查最大数量限制
        if ($this->maxQuantity !== null && $newQuantity > $this->maxQuantity) {
            return false;
        }
        
        // 检查是否会变成负数
        if ($newQuantity < 0) {
            return false;
        }
        
        // 检查单次操作限制
        if ($changeQuantity > 0 && $this->maxIncreaseQuantity !== null && $changeQuantity > $this->maxIncreaseQuantity) {
            return false;
        }
        
        if ($changeQuantity < 0 && $this->maxDecreaseQuantity !== null && abs($changeQuantity) > $this->maxDecreaseQuantity) {
            return false;
        }
        
        return true;
    }
}

<?php

namespace Dybasedev\LunaPrototype\Foundation\Exception;

/**
 * 业务异常基类
 * 
 * 继承自 LunaException，专门用于表示业务逻辑中的预期异常情况。
 * 这类异常默认不需要记录日志，且消息直接显示给用户。
 * 
 * @package Dybasedev\LunaPrototype\Foundation\Exception
 */
class BusinessException extends LunaException
{
    /**
     * 构造函数
     * 
     * 业务异常默认使用消息作为显示消息，不需要报告。
     * 
     * @param string $message 错误消息（直接显示给用户）
     * @param mixed $code 错误代码
     * @param \Throwable|null $previous 前一个异常
     */
    public function __construct(string $message = "", mixed $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        
        // 业务异常的消息直接显示给用户
        $this->withDisplayMessage($message);
        
        // 业务异常默认不报告
        $this->dontReport();
        
        // 默认使用 400 状态码
        $this->withHttpStatus(400);
    }

    /**
     * 创建业务异常实例
     * 
     * @param string $message 错误消息（面向用户）
     * @param int $code 错误代码
     * @param int $httpStatus HTTP 状态码
     * @return static
     */
    public static function make(string $message, int $code = 0, int $httpStatus = 400): static
    {
        return static::create($message, $code)
            ->withHttpStatus($httpStatus)
            ->usePrevious(false);
    }

    /**
     * 创建带有额外信息的业务异常
     * 
     * @param string $message 错误消息
     * @param array $data 携带的数据
     * @param int $httpStatus HTTP 状态码
     * @return static
     */
    public static function withInfo(string $message, array $data, int $httpStatus = 400): static
    {
        return static::make($message, 0, $httpStatus)
            ->withData($data);
    }

    /**
     * 创建余额不足异常
     * 
     * @param float $required 需要的金额
     * @param float $available 可用金额
     * @return static
     */
    public static function insufficientBalance(float $required, float $available): static
    {
        return static::make("余额不足，需要 {$required} 元，当前余额 {$available} 元")
            ->withData([
                'required' => $required,
                'available' => $available,
                'shortage' => $required - $available,
            ])
            ->withBehaviour([
                'action' => 'show_recharge',
                'amount' => $required - $available,
            ]);
    }

    /**
     * 创建库存不足异常
     * 
     * @param int $requested 请求数量
     * @param int $available 可用数量
     * @param array $product 产品信息
     * @return static
     */
    public static function insufficientStock(int $requested, int $available, array $product = []): static
    {
        $message = isset($product['name']) 
            ? "商品「{$product['name']}」库存不足，仅剩 {$available} 件"
            : "库存不足，仅剩 {$available} 件";

        return static::make($message)
            ->withData([
                'requested' => $requested,
                'available' => $available,
                'product' => $product,
            ])
            ->withBehaviour([
                'action' => 'update_quantity',
                'max' => $available,
            ]);
    }

    /**
     * 创建资源不存在异常
     * 
     * @param string $resource 资源名称
     * @param mixed $id 资源ID
     * @return static
     */
    public static function notFound(string $resource, mixed $id = null): static
    {
        $message = $id ? "{$resource}（ID: {$id}）不存在" : "{$resource}不存在";
        
        return static::make($message, 0, 404)
            ->withData(compact('resource', 'id'));
    }

    /**
     * 创建操作不允许异常
     * 
     * @param string $reason 原因说明
     * @return static
     */
    public static function forbidden(string $reason): static
    {
        return static::make($reason, 0, 403);
    }

    /**
     * 创建验证失败异常
     * 
     * @param string $message 错误消息
     * @param array $errors 验证错误详情
     * @return static
     */
    public static function validationFailed(string $message, array $errors): static
    {
        return static::make($message, 0, 422)
            ->withData(['errors' => $errors])
            ->withBehaviour(['action' => 'show_validation_errors']);
    }

    /**
     * 创建重复操作异常
     * 
     * @param string $operation 操作名称
     * @param int $cooldown 冷却时间（秒）
     * @return static
     */
    public static function duplicateOperation(string $operation = '操作', int $cooldown = 0): static
    {
        $message = $cooldown > 0 
            ? "请勿重复{$operation}，请在 {$cooldown} 秒后重试"
            : "请勿重复{$operation}";

        return static::make($message)
            ->withData(['cooldown' => $cooldown])
            ->withBehaviour(['action' => 'disable_submit', 'duration' => $cooldown]);
    }

    /**
     * 创建服务不可用异常
     * 
     * @param string $service 服务名称
     * @param int $retryAfter 建议重试时间（秒）
     * @return static
     */
    public static function serviceUnavailable(string $service = '服务', int $retryAfter = 60): static
    {
        return static::make("{$service}暂时不可用，请稍后重试", 0, 503)
            ->withData(['service' => $service, 'retry_after' => $retryAfter])
            ->withBehaviour(['action' => 'retry_later', 'delay' => $retryAfter]);
    }
}
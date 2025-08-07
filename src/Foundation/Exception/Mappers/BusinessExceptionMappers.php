<?php

namespace Dybasedev\LunaPrototype\Foundation\Exception\Mappers;

use Dybasedev\LunaPrototype\Foundation\Exception\BusinessException;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaExceptionMapperBuilder;

/**
 * 业务异常映射模板
 * 
 * 处理自定义的业务异常，提供灵活的映射配置。
 * 
 * @package Dybasedev\LunaPrototype\Foundation\Exception\Mappers
 */
class BusinessExceptionMappers
{
    /**
     * 通用业务异常映射
     * 
     * 自动识别 BusinessException 的属性并进行映射。
     * 
     * @return LunaExceptionMapperBuilder
     * 
     * @example
     * ```php
     * // 注册映射
     * $configure->wrap(BusinessExceptionMappers::general());
     * 
     * // 抛出业务异常
     * throw BusinessException::create('库存不足')
     *     ->withStatusCode(400)
     *     ->withData(['available' => 10, 'requested' => 20])
     *     ->withBehaviour(['action' => 'show_stock_warning']);
     * ```
     */
    public static function general(): LunaExceptionMapperBuilder
    {
        return LunaExceptionMapperBuilder::for(BusinessException::class)
            ->message(fn(BusinessException $e) => $e->displayMessage ?? $e->getMessage())
            ->httpStatus(fn(BusinessException $e) => $e->httpStatus)
            ->reportable(fn(BusinessException $e) => $e->reportable)
            ->behaviour(fn(BusinessException $e) => $e->behaviour)
            ->data(fn(BusinessException $e) => $e->data);
    }

    /**
     * 创建特定业务场景的异常映射
     * 
     * @param string $exceptionClass 业务异常类名
     * @param string|null $defaultMessage 默认消息
     * @param int $defaultStatusCode 默认状态码
     * @return LunaExceptionMapperBuilder
     * 
     * @example
     * ```php
     * // 为特定的业务异常类创建映射
     * $configure->wrap(
     *     BusinessExceptionMappers::forScenario(
     *         InsufficientBalanceException::class,
     *         '余额不足',
     *         400
     *     )
     * );
     * ```
     */
    public static function forScenario(
        string $exceptionClass,
        ?string $defaultMessage = null,
        int $defaultStatusCode = 400
    ): LunaExceptionMapperBuilder {
        $builder = LunaExceptionMapperBuilder::for($exceptionClass)
            ->httpStatus($defaultStatusCode)
            ->dontReport();

        if ($defaultMessage) {
            $builder->message($defaultMessage);
        } else {
            $builder->message(fn($e) => $e->getMessage());
        }

        // 如果是 BusinessException 的子类，尝试获取其属性
        if (is_subclass_of($exceptionClass, BusinessException::class)) {
            $builder->behaviour(fn($e) => $e->behaviour ?? null)
                    ->data(fn($e) => $e->data ?? null)
                    ->reportable(fn($e) => $e->reportable ?? false);
        }

        return $builder;
    }

    /**
     * 常见业务异常场景预设
     * 
     * @return array<string, LunaExceptionMapperBuilder>
     */
    public static function commonScenarios(): array
    {
        return [
            'insufficient_balance' => static::forScenario(
                BusinessException::class,
                '余额不足',
                400
            )->behaviour(['action' => 'show_recharge']),

            'stock_shortage' => static::forScenario(
                BusinessException::class,
                '库存不足',
                400
            )->behaviour(['action' => 'show_stock_alert']),

            'order_expired' => static::forScenario(
                BusinessException::class,
                '订单已过期',
                400
            )->behaviour(['action' => 'refresh_order']),

            'duplicate_operation' => static::forScenario(
                BusinessException::class,
                '请勿重复操作',
                400
            )->behaviour(['action' => 'disable_submit']),

            'service_unavailable' => static::forScenario(
                BusinessException::class,
                '服务暂时不可用',
                503
            )->behaviour(['action' => 'retry_later']),
        ];
    }
}
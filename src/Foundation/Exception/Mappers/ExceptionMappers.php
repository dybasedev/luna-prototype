<?php

namespace Dybasedev\LunaPrototype\Foundation\Exception\Mappers;

use Dybasedev\LunaPrototype\Foundation\Exception\LunaExceptionMapperBuilder;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * 预定义的异常映射模板
 * 
 * 提供常见 Laravel 异常的映射模板，可以直接使用或作为参考自定义。
 * 所有模板方法返回 LunaExceptionMapperBuilder 实例，支持进一步自定义。
 * 
 * @package Dybasedev\LunaPrototype\Foundation\Exception\Mappers
 */
class ExceptionMappers
{
    /**
     * 验证异常映射
     * 
     * 将 Laravel 的验证异常转换为友好的验证错误响应。
     * 默认返回 422 状态码，不记录日志，并携带验证错误详情。
     * 
     * @return LunaExceptionMapperBuilder
     * 
     * @example
     * ```php
     * // 直接使用
     * $configure->wrap(ExceptionMappers::validation());
     * 
     * // 自定义消息
     * $configure->wrap(
     *     ExceptionMappers::validation()
     *         ->message('请检查您的输入')
     * );
     * ```
     */
    public static function validation(): LunaExceptionMapperBuilder
    {
        return LunaExceptionMapperBuilder::for(ValidationException::class)
            ->message('验证失败')
            ->httpStatus(422)
            ->dontReport()
            ->behaviour(['action' => 'show_validation_errors'])
            ->data(function (ValidationException $e) {
                return [
                    'errors' => $e->errors(),
                    'message' => $e->getMessage(),
                ];
            });
    }

    /**
     * 认证异常映射
     * 
     * 处理用户未登录的情况，默认返回 401 状态码。
     * 前端通常需要跳转到登录页面。
     * 
     * @param string|null $redirectUrl 登录页面URL，null则由前端决定
     * @return LunaExceptionMapperBuilder
     * 
     * @example
     * ```php
     * // 使用默认配置
     * $configure->wrap(ExceptionMappers::authentication());
     * 
     * // 指定登录页面
     * $configure->wrap(ExceptionMappers::authentication('/login'));
     * ```
     */
    public static function authentication(?string $redirectUrl = null): LunaExceptionMapperBuilder
    {
        $behaviour = ['action' => 'redirect_to_login'];
        if ($redirectUrl) {
            $behaviour['url'] = $redirectUrl;
        }

        return LunaExceptionMapperBuilder::for(AuthenticationException::class)
            ->message('请先登录')
            ->httpStatus(401)
            ->dontReport()
            ->behaviour($behaviour);
    }

    /**
     * 授权异常映射
     * 
     * 处理用户无权限访问的情况，默认返回 403 状态码。
     * 
     * @return LunaExceptionMapperBuilder
     * 
     * @example
     * ```php
     * $configure->wrap(ExceptionMappers::authorization());
     * ```
     */
    public static function authorization(): LunaExceptionMapperBuilder
    {
        return LunaExceptionMapperBuilder::for(AuthorizationException::class)
            ->message('您没有权限执行此操作')
            ->httpStatus(403)
            ->dontReport()
            ->behaviour(['action' => 'show_permission_denied']);
    }

    /**
     * 模型未找到异常映射
     * 
     * 处理 Eloquent 模型未找到的情况，默认返回 404 状态码。
     * 支持自定义资源名称。
     * 
     * @param string $resourceName 资源名称，用于生成友好的错误消息
     * @return LunaExceptionMapperBuilder
     * 
     * @example
     * ```php
     * // 通用配置
     * $configure->wrap(ExceptionMappers::modelNotFound());
     * 
     * // 指定资源名称
     * $configure->wrap(ExceptionMappers::modelNotFound('用户'));
     * ```
     */
    public static function modelNotFound(string $resourceName = '资源'): LunaExceptionMapperBuilder
    {
        return LunaExceptionMapperBuilder::for(ModelNotFoundException::class)
            ->message(function (ModelNotFoundException $e) use ($resourceName) {
                // 尝试从异常中获取模型类型
                $model = $e->getModel();
                if ($model) {
                    $modelName = class_basename($model);
                    // 可以在这里添加模型名称到中文的映射
                    $nameMap = [
                        'User' => '用户',
                        'Post' => '文章',
                        'Order' => '订单',
                        // 添加更多映射...
                    ];
                    $resourceName = $nameMap[$modelName] ?? $resourceName;
                }
                
                return "{$resourceName}不存在";
            })
            ->httpStatus(404)
            ->dontReport()
            ->data(function (ModelNotFoundException $e) {
                return [
                    'model' => $e->getModel(),
                    'ids' => $e->getIds(),
                ];
            });
    }

    /**
     * HTTP 404 异常映射
     * 
     * 处理路由未找到的情况。
     * 
     * @return LunaExceptionMapperBuilder
     * 
     * @example
     * ```php
     * $configure->wrap(ExceptionMappers::notFound());
     * ```
     */
    public static function notFound(): LunaExceptionMapperBuilder
    {
        return LunaExceptionMapperBuilder::for(NotFoundHttpException::class)
            ->message('页面不存在')
            ->httpStatus(404)
            ->dontReport()
            ->behaviour(['action' => 'redirect_to_home']);
    }

    /**
     * 请求方法不允许异常映射
     * 
     * 处理 HTTP 方法不正确的情况，如用 GET 请求 POST 接口。
     * 
     * @return LunaExceptionMapperBuilder
     * 
     * @example
     * ```php
     * $configure->wrap(ExceptionMappers::methodNotAllowed());
     * ```
     */
    public static function methodNotAllowed(): LunaExceptionMapperBuilder
    {
        return LunaExceptionMapperBuilder::for(MethodNotAllowedHttpException::class)
            ->message('请求方法不正确')
            ->httpStatus(405)
            ->dontReport();
    }

    /**
     * 请求频率限制异常映射
     * 
     * 处理请求过于频繁的情况，默认返回 429 状态码。
     * 
     * @return LunaExceptionMapperBuilder
     * 
     * @example
     * ```php
     * $configure->wrap(ExceptionMappers::throttle());
     * ```
     */
    public static function throttle(): LunaExceptionMapperBuilder
    {
        return LunaExceptionMapperBuilder::for(ThrottleRequestsException::class)
            ->message(function (ThrottleRequestsException $e) {
                $retryAfter = $e->getHeaders()['Retry-After'] ?? 60;
                return "请求过于频繁，请在 {$retryAfter} 秒后重试";
            })
            ->httpStatus(429)
            ->dontReport()
            ->data(function (ThrottleRequestsException $e) {
                return [
                    'retry_after' => $e->getHeaders()['Retry-After'] ?? 60,
                ];
            })
            ->behaviour(['action' => 'show_rate_limit']);
    }

    /**
     * 数据库查询异常映射
     * 
     * 处理数据库查询错误，通常需要记录日志。
     * 在生产环境中应该返回通用错误消息，避免泄露数据库结构。
     * 
     * @param bool $debug 是否显示详细错误信息（仅在开发环境使用）
     * @return LunaExceptionMapperBuilder
     * 
     * @example
     * ```php
     * // 生产环境
     * $configure->wrap(ExceptionMappers::queryException());
     * 
     * // 开发环境
     * $configure->wrap(ExceptionMappers::queryException(app()->isLocal()));
     * ```
     */
    public static function queryException(bool $debug = false): LunaExceptionMapperBuilder
    {
        return LunaExceptionMapperBuilder::for(QueryException::class)
            ->message(function (QueryException $e) use ($debug) {
                if ($debug) {
                    return '数据库错误: ' . $e->getMessage();
                }
                
                // 检查特定的数据库错误
                $errorCode = $e->errorInfo[1] ?? null;
                
                return match ($errorCode) {
                    1062 => '数据重复，请检查输入',
                    1451 => '无法删除，存在关联数据',
                    1452 => '无法添加，缺少关联数据',
                    default => '数据库操作失败，请稍后重试',
                };
            })
            ->httpStatus(500)
            ->reportable(true); // 数据库错误通常需要记录
    }

    /**
     * 请求频率限制异常映射（Symfony 版本）
     * 
     * 处理 Symfony 的请求频率限制异常。
     * 
     * @return LunaExceptionMapperBuilder
     * 
     * @example
     * ```php
     * $configure->wrap(ExceptionMappers::tooManyRequests());
     * ```
     */
    public static function tooManyRequests(): LunaExceptionMapperBuilder
    {
        return LunaExceptionMapperBuilder::for(TooManyRequestsHttpException::class)
            ->message('请求过于频繁，请稍后重试')
            ->httpStatus(429)
            ->dontReport()
            ->behaviour(['action' => 'show_rate_limit']);
    }

    /**
     * 创建所有常见异常的默认映射
     * 
     * 返回一个包含所有预定义映射的数组，可以直接应用到配置中。
     * 
     * @param array $options 配置选项
     *   - 'debug' => bool 是否启用调试模式（显示详细错误）
     *   - 'login_url' => string 登录页面URL
     * @return array<LunaExceptionMapperBuilder>
     * 
     * @example
     * ```php
     * // 应用所有默认映射
     * foreach (ExceptionMappers::defaults() as $mapper) {
     *     $configure->wrap($mapper);
     * }
     * 
     * // 使用自定义选项
     * $mappers = ExceptionMappers::defaults([
     *     'debug' => app()->isLocal(),
     *     'login_url' => '/auth/login'
     * ]);
     * ```
     */
    public static function defaults(array $options = []): array
    {
        $debug = $options['debug'] ?? false;
        $loginUrl = $options['login_url'] ?? null;

        return [
            static::validation(),
            static::authentication($loginUrl),
            static::authorization(),
            static::modelNotFound(),
            static::notFound(),
            static::methodNotAllowed(),
            static::throttle(),
            static::tooManyRequests(),
            static::queryException($debug),
        ];
    }
}
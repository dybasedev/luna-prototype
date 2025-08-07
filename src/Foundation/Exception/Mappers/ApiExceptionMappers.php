<?php

namespace Dybasedev\LunaPrototype\Foundation\Exception\Mappers;

use Dybasedev\LunaPrototype\Foundation\Exception\LunaExceptionMapperBuilder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * API 相关的异常映射模板
 * 
 * 提供专门用于 API 开发的异常映射，遵循 RESTful 规范。
 * 
 * @package Dybasedev\LunaPrototype\Foundation\Exception\Mappers
 */
class ApiExceptionMappers
{
    /**
     * 错误请求异常映射
     * 
     * 处理 400 Bad Request 错误，通常是请求参数格式错误。
     * 
     * @return LunaExceptionMapperBuilder
     */
    public static function badRequest(): LunaExceptionMapperBuilder
    {
        return LunaExceptionMapperBuilder::for(BadRequestHttpException::class)
            ->message('请求参数错误')
            ->httpStatus(400)
            ->dontReport();
    }

    /**
     * 冲突异常映射
     * 
     * 处理 409 Conflict 错误，通常是资源状态冲突。
     * 
     * @return LunaExceptionMapperBuilder
     */
    public static function conflict(): LunaExceptionMapperBuilder
    {
        return LunaExceptionMapperBuilder::for(ConflictHttpException::class)
            ->message('资源状态冲突')
            ->httpStatus(409)
            ->dontReport();
    }

    /**
     * 无法处理的实体异常映射
     * 
     * 处理 422 Unprocessable Entity 错误，通常是业务逻辑验证失败。
     * 
     * @return LunaExceptionMapperBuilder
     */
    public static function unprocessableEntity(): LunaExceptionMapperBuilder
    {
        return LunaExceptionMapperBuilder::for(UnprocessableEntityHttpException::class)
            ->message('请求无法处理')
            ->httpStatus(422)
            ->dontReport();
    }

    /**
     * 请求体过大异常映射
     * 
     * 处理上传文件或请求体超过限制的情况。
     * 
     * @return LunaExceptionMapperBuilder
     */
    public static function postTooLarge(): LunaExceptionMapperBuilder
    {
        return LunaExceptionMapperBuilder::for(PostTooLargeException::class)
            ->message(function (PostTooLargeException $e) {
                $maxSize = ini_get('post_max_size');
                return "请求数据过大，最大允许 {$maxSize}";
            })
            ->httpStatus(413)
            ->dontReport()
            ->data(function () {
                return [
                    'max_size' => ini_get('post_max_size'),
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                ];
            });
    }

    /**
     * HTTP 响应异常映射
     * 
     * 处理手动抛出的 HTTP 响应异常。
     * 
     * @return LunaExceptionMapperBuilder
     */
    public static function httpResponse(): LunaExceptionMapperBuilder
    {
        return LunaExceptionMapperBuilder::for(HttpResponseException::class)
            ->message(function (HttpResponseException $e) {
                $response = $e->getResponse();
                if ($response instanceof \Illuminate\Http\JsonResponse) {
                    $data = $response->getData(true);
                    return $data['message'] ?? '请求处理失败';
                }
                return '请求处理失败';
            })
            ->httpStatus(function (HttpResponseException $e) {
                return $e->getResponse()->getStatusCode();
            })
            ->dontReport();
    }

    /**
     * 创建 API 专用的异常映射集合
     * 
     * @return array<LunaExceptionMapperBuilder>
     */
    public static function all(): array
    {
        return [
            static::badRequest(),
            static::conflict(),
            static::unprocessableEntity(),
            static::postTooLarge(),
            static::httpResponse(),
        ];
    }
}
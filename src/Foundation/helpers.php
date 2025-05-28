<?php

use Dybasedev\LunaPrototype\Foundation\Configuration\ConfigurationGroup;
use Dybasedev\LunaPrototype\Foundation\Configuration\LunaConfiguration;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaExceptionMapperBuilder;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;

if (!function_exists('hash_code')) {
    function hash_code($str): int
    {
        $hash = 0;
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $hash = ($hash * 31 + ord($str[$i])) & 0xFFFFFFFF;
        }
        return $hash;
    }
}

if (!function_exists('short_hash_code')) {
    function short_hash_code($str): int
    {
        $hash = 0;
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $hash = ($hash * 31 + ord($str[$i])) % 255;
        }
        return $hash;
    }
}

if (!function_exists('luna_config')) {
    /**
     * @param string|null $group
     * @return ($group is null ? LunaConfiguration : ConfigurationGroup)
     */
    function luna_config(?string $group = null): LunaConfiguration|ConfigurationGroup
    {
        /** @var LunaConfiguration $configuration */
        $configuration = app(LunaConfiguration::class);

        if ($group) {
            return $configuration->group($group);
        }

        return $configuration;
    }
}


if (!function_exists('luna_response')) {
    /**
     * JSON 响应消息
     *
     * @param string $message 消息
     * @param mixed $data 数据
     * @param array|null $behaviour 客户端建议的行为
     * @param bool $success 是否成功
     * @param int $httpStatus HTTP 状态码
     * @return JsonResponse
     */
    function luna_response(
        string $message,
        mixed $data,
        array|null $behaviour = null,
        bool $success = true,
        int $httpStatus = 200
    ): JsonResponse {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'behaviour' => $behaviour,
            'data' => $data
        ], $httpStatus);
    }
}

if (!function_exists('err')) {
    /**
     * 错误响应
     *
     * @param Throwable|string $throwable 异常
     * @return JsonResponse
     */
    function err(Throwable|string $throwable): JsonResponse
    {
        if (is_string($throwable)) {
            return err(new LunaException($throwable));
        }

        if (!$throwable instanceof LunaException) {
            $throwable = LunaException::create($throwable);
        }

        return luna_response(
            $throwable->displayMessage ?: $throwable->getMessage(),
            $throwable->data,
            $throwable->behaviour,
            false,
            $throwable->httpStatus
        );
    }
}

if (!function_exists('ok')) {
    /**
     * 成功响应
     *
     * @param mixed $data 数据
     * @param array|null $behaviour 客户端建议的行为
     * @param string $message 消息
     * @return JsonResponse
     */
    function ok(mixed $data = null, array|null $behaviour = null, string $message = ''): JsonResponse
    {
        return luna_response($message, $data, $behaviour);
    }
}

if (!function_exists('luna_module_configure')) {
    /**
     * 获取模块配置
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $configure
     * @return TClass
     * @throws BindingResolutionException
     */
    function luna_module_configure(string $configure)
    {
        return app()->make($configure);
    }
}

if (!function_exists('luna_handler')) {
    /**
     * 获取 LunaHandler 对象
     *
     * @return LunaHandler
     */
    function luna_handler(): LunaHandler
    {
        return app('luna.handler');
    }
}

if (!function_exists('luna_exception_mapper')) {
    /**
     * 获取异常映射器
     *
     * @template TClass of Throwable
     * @param class-string<TClass> $exceptionClass
     * @return LunaExceptionMapperBuilder<TClass>
     */
    function luna_exception_mapper(string $exceptionClass): LunaExceptionMapperBuilder
    {
        return new LunaExceptionMapperBuilder($exceptionClass);
    }
}
<?php

/**
 * Luna 原型框架全局辅助函数
 *
 * 这个文件包含了 Luna 框架中常用的全局辅助函数，
 * 提供了便捷的方式来访问框架的核心功能。
 *
 * @package Dybasedev\LunaPrototype\Foundation
 * @author Luna Prototype Team
 * @since 1.0.0
 */

use Dybasedev\LunaPrototype\Foundation\BusinessEvent\LunaBusinessEvent;
use Dybasedev\LunaPrototype\Foundation\Configuration\ConfigurationGroup;
use Dybasedev\LunaPrototype\Foundation\Configuration\LunaConfiguration;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaExceptionMapperBuilder;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\LunaApplication;
use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

if (!function_exists('hash_code')) {
    /**
     * 生成字符串的哈希码
     *
     * 使用 Java 风格的哈希算法生成字符串的哈希码。
     * 这个函数在 Luna 框架中用于生成各种标识符的数字 ID。
     *
     * 算法特点：
     * - 使用 31 作为乘数因子（质数，能够减少冲突）
     * - 使用位运算确保结果在 32 位整数范围内
     * - 对于相同的输入总是返回相同的输出
     *
     * @param string $str 要生成哈希码的字符串
     * @return int 生成的哈希码（32位整数）
     * @throws InvalidArgumentException 当输入不是字符串时抛出
     */
    function hash_code(string $str): int
    {
        if ($str === '') {
            return 0;
        }

        $hash = 0;
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $hash = ($hash * 31 + ord($str[$i])) & 0xFFFFFFFF;
        }
        return $hash;
    }
}

if (!function_exists('short_hash_code')) {
    /**
     * 生成字符串的短哈希码
     *
     * 与 hash_code 函数类似，但结果被限制在 0-254 范围内。
     * 这个函数用于需要较小数值范围的场景。
     *
     * 算法特点：
     * - 使用与 hash_code 相同的基本算法
     * - 使用模运算将结果限制在 0-254 范围内
     * - 冲突概率相对较高，但数值范围较小
     *
     * @param string $str 要生成短哈希码的字符串
     * @return int 生成的短哈希码（0-254 范围内）
     * @throws InvalidArgumentException 当输入不是字符串时抛出
     */
    function short_hash_code(string $str): int
    {
        if ($str === '') {
            return 0;
        }

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
     * 获取 Luna 配置对象
     *
     * 这个函数提供了访问 Luna 配置系统的便捷方式。
     * 可以获取整个配置对象或特定配置组。
     *
     * 使用示例：
     * ```php
     * // 获取整个配置对象
     * $config = luna_config();
     * 
     * // 获取特定配置组
     * $groupConfig = luna_config('database');
     * ```
     *
     * @param string|null $group 配置组名称，如果为 null 则返回整个配置对象
     * @return ($group is null ? LunaConfiguration : ConfigurationGroup) 配置对象或配置组
     * @throws BindingResolutionException 当无法解析配置对象时抛出
     */
    function luna_config(?string $group = null): LunaConfiguration|ConfigurationGroup
    {
        try {
            /** @var LunaConfiguration $configuration */
            $configuration = app(LunaConfiguration::class);

            if ($group !== null) {
                if (!is_string($group) || trim($group) === '') {
                    throw new InvalidArgumentException('Configuration group name must be a non-empty string');
                }
                return $configuration->group($group);
            }

            return $configuration;
        } catch (BindingResolutionException $e) {
            throw new BindingResolutionException('Unable to resolve Luna configuration: ' . $e->getMessage(), 0, $e);
        }
    }
}


if (!function_exists('luna_response')) {
    /**
     * 生成标准化的 JSON 响应
     *
     * 这个函数创建符合 Luna 框架标准的 JSON 响应格式。
     * 所有的 API 响应都应该使用这个函数来保持一致性。
     *
     * 响应格式：
     * ```json
     * {
     *   "success": boolean,
     *   "message": string,
     *   "behaviour": array|null,
     *   "data": mixed
     * }
     * ```
     *
     * @param string $message 响应消息，用于描述操作结果
     * @param mixed $data 响应数据，可以是任何类型的数据
     * @param array|null $behaviour 客户端建议的行为，用于指导前端操作
     * @param bool $success 操作是否成功，默认为 true
     * @param int $httpStatus HTTP 状态码，默认为 200
     * @return JsonResponse 格式化的 JSON 响应
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
     * 生成错误响应
     *
     * 这个函数提供了一种统一的方式来处理错误响应。
     * 它可以接受异常对象或错误消息字符串，并自动转换为标准的错误响应格式。
     *
     * 错误处理流程：
     * 1. 如果传入字符串，则创建 LunaException 对象
     * 2. 如果传入非 LunaException 异常，则转换为 LunaException
     * 3. 使用异常的信息生成标准错误响应
     *
     * @param Throwable|string $throwable 异常对象或错误消息字符串
     * @return JsonResponse 格式化的错误响应
     */
    function err(Throwable|string $throwable): JsonResponse
    {
        try {
            // 如果是字符串，转换为 LunaException
            if (is_string($throwable)) {
                return err(new LunaException($throwable));
            }

            // 如果不是 LunaException，则转换为 LunaException
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
        } catch (Throwable $e) {
            // 确保错误处理本身不会抛出异常
            Log::error('Failed to generate error response', [
                'original_error' => is_string($throwable) ? $throwable : $throwable->getMessage(),
                'processing_error' => $e->getMessage(),
            ]);

            // 返回一个安全的错误响应
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing the request',
                'data' => null,
                'behaviour' => null,
            ], 500);
        }
    }
}

if (!function_exists('ok')) {
    /**
     * 生成成功响应
     *
     * 这个函数是 luna_response 的简化版本，专门用于生成成功响应。
     * 它自动设置 success 为 true，并提供了更简洁的参数顺序。
     *
     * 使用示例：
     * ```php
     * // 简单的成功响应
     * return ok($userData);
     * 
     * // 带消息的成功响应
     * return ok($userData, null, '用户信息获取成功');
     * 
     * // 带行为建议的成功响应
     * return ok($userData, ['redirect' => '/dashboard']);
     * ```
     *
     * @param mixed $data 响应数据，默认为 null
     * @param array|null $behaviour 客户端建议的行为，默认为 null
     * @param string $message 响应消息，默认为空字符串
     * @return JsonResponse 格式化的成功响应
     */
    function ok(mixed $data = null, array|null $behaviour = null, string $message = ''): JsonResponse
    {
        return luna_response($message, $data, $behaviour);
    }
}

if (!function_exists('luna_module_configure')) {
    /**
     * 获取模块配置对象
     *
     * 这个函数提供了一种便捷的方式来获取模块配置对象。
     * 它通过服务容器来解析配置类，并自动处理依赖注入。
     *
     * 使用示例：
     * ```php
     * // 获取资产账户模块配置
     * $config = luna_module_configure(LunaAssetsAccountConfigure::class);
     * 
     * // 获取处理器配置
     * $handlerConfig = luna_module_configure(LunaHandlerConfigure::class);
     * ```
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $configure 配置类的完全限定名
     * @return TClass 配置对象实例
     * @throws BindingResolutionException 当服务容器无法解析类时抛出
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
     * 这个函数提供了获取 Luna 处理器管理对象的便捷方式。
     * 处理器管理对象用于管理和执行各种业务处理器。
     *
     * 使用示例：
     * ```php
     * // 获取处理器管理对象
     * $handler = luna_handler();
     * 
     * // 获取特定的处理器
     * $accountHandler = $handler->getHandler('account_handler');
     * ```
     *
     * @return LunaHandler 处理器管理对象实例
     */
    function luna_handler(): LunaHandler
    {
        return app('luna.handler');
    }
}

if (!function_exists('luna_configuration')) {
    /**
     * 获取 Luna 配置管理对象
     *
     * Luna 配置管理系统提供了灵活的配置存储和版本控制功能，支持分组管理、
     * 缓存优化和动态配置更新。配置数据存储在数据库中，支持配置版本历史记录。
     *
     * 主要功能：
     * - 分组管理：将配置项按业务逻辑分组，便于组织和访问
     * - 版本控制：每次配置修改都会创建新版本，可追溯历史
     * - 缓存支持：自动缓存配置项，提升读取性能
     * - 动态更新：支持运行时修改配置并持久化到数据库
     * - 点式访问：支持使用点语法访问嵌套配置项
     *
     * 使用示例：
     * ```php
     * // 获取配置管理实例
     * $config = luna_configuration();
     * 
     * // 创建配置组和配置项
     * $appGroup = $config->group('app');
     * $appGroup->create('settings', '应用设置', [
     *     'theme' => 'light',
     *     'language' => 'zh-CN',
     *     'features' => [
     *         'darkMode' => true,
     *         'notifications' => true
     *     ]
     * ]);
     * 
     * // 读取配置
     * $theme = $appGroup->get('settings.theme'); // 'light'
     * $features = $appGroup->get('settings.features'); // 数组
     * 
     * // 更新配置
     * $appGroup->set('settings.theme', 'dark');
     * $appGroup->set('settings.features.notifications', false);
     * 
     * // 保存配置（创建新版本）
     * $appGroup->save();
     * 
     * // 检查配置是否存在
     * if ($appGroup->exists('settings')) {
     *     // 配置存在
     * }
     * ```
     *
     * @return LunaConfiguration 配置管理对象实例
     * @see ConfigurationGroup 配置组管理类
     * @see Repository 配置仓库类
     */
    function luna_configuration(): LunaConfiguration
    {
        return app('luna.config');
    }
}

if (!function_exists('luna_exception_mapper')) {
    /**
     * 创建异常映射器构建器
     *
     * 这个函数创建一个异常映射器构建器，用于配置特定异常类型的处理方式。
     * 异常映射器可以将系统异常转换为用户友好的错误响应。
     *
     * 使用示例：
     * ```php
     * // 为特定异常类型创建映射器
     * $mapper = luna_exception_mapper(ValidationException::class)
     *     ->message('验证失败')
     *     ->httpStatus(422);
     * ```
     *
     * @template TClass of Throwable
     * @param class-string<TClass> $exceptionClass 异常类的完全限定名
     * @return LunaExceptionMapperBuilder<TClass> 异常映射器构建器实例
     */
    function luna_exception_mapper(string $exceptionClass): LunaExceptionMapperBuilder
    {
        return new LunaExceptionMapperBuilder($exceptionClass);
    }
}

if (!function_exists('luna_business_event')) {
    /**
     * 获取业务事件对象
     *
     * 这个函数提供了获取 Luna 业务事件管理对象的便捷方式。
     * 业务事件系统用于处理应用程序中的各种业务事件。
     *
     * 使用示例：
     * ```php
     * // 获取业务事件管理对象
     * $businessEvent = luna_business_event();
     * 
     * // 触发业务事件
     * $businessEvent->trigger('user.created', $userData);
     * ```
     *
     * @return LunaBusinessEvent 业务事件管理对象实例
     */
    function luna_business_event(): LunaBusinessEvent
    {
        return app('luna.business-event');
    }
}

if (!function_exists('luna_app')) {
    /**
     * 获取 Luna 应用程序实例
     *
     * 提供对 Luna 应用程序主实例的快速访问。
     * 通过这个辅助函数可以方便地使用应用程序的各种功能。
     *
     * 使用示例：
     * ```php
     * // 获取应用程序实例
     * $app = luna_app();
     * 
     * // 导出备份数据
     * $backup = $app->exportBackup();
     * 
     * // 导入备份数据
     * $result = $app->importBackup($backupData);
     * ```
     *
     * @return LunaApplication 应用程序实例
     */
    function luna_app(): LunaApplication
    {
        return app('luna');
    }
}

if (!function_exists('luna_registered_modules')) {
    /**
     * 获取已注册的 Luna 模块列表
     *
     * 返回所有已注册的 Luna 模块配置对象的数组。
     * 键为模块名称，值为对应的配置对象实例。
     *
     * 使用示例：
     * ```php
     * // 获取所有已注册的模块
     * $modules = luna_registered_modules();
     * 
     * // 检查特定模块是否已注册
     * if (isset($modules['luna.assets-account'])) {
     *     // 模块已注册
     * }
     * 
     * // 遍历所有模块
     * foreach ($modules as $name => $configure) {
     *     echo $name . ': ' . get_class($configure) . PHP_EOL;
     * }
     * ```
     *
     * @return array<string, LunaModuleConfigure> 已注册的模块配置对象数组
     */
    function luna_registered_modules(): array
    {
        try {
            return app('luna.registered-modules');
        } catch (BindingResolutionException $e) {
            // 如果服务容器中没有注册模块列表，返回空数组
            return [];
        }
    }
}
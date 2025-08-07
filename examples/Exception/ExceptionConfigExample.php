<?php

use Dybasedev\LunaPrototype\Foundation\Exception\LunaExceptionConfigure;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaExceptionMapperBuilder;
use Dybasedev\LunaPrototype\Foundation\Exception\Mappers\ExceptionMappers;
use Dybasedev\LunaPrototype\Foundation\Exception\Mappers\ApiExceptionMappers;
use Dybasedev\LunaPrototype\Foundation\Exception\Mappers\BusinessExceptionMappers;
use Illuminate\Support\ServiceProvider;

/**
 * 异常配置示例
 * 
 * 展示如何在实际项目中配置异常映射。
 * 将此文件复制到你的项目并根据需要修改。
 */
class ExceptionConfigExample extends ServiceProvider
{
    /**
     * 注册服务
     */
    public function register(): void
    {
        //
    }

    /**
     * 启动服务
     */
    public function boot(): void
    {
        $this->configureExceptions();
    }

    /**
     * 配置异常映射
     */
    protected function configureExceptions(): void
    {
        /** @var LunaExceptionConfigure $configure */
        $configure = $this->app->make(LunaExceptionConfigure::class);

        // 1. 配置全局行为
        if ($this->app->runningInConsole()) {
            return; // CLI 环境不需要配置
        }

        // API 应用总是返回 JSON
        $configure->alwaysJsonRender();

        // 自定义报告器（可选）
        $configure->reporter(function (\Throwable $e) {
            // 这里可以集成第三方错误跟踪服务
            // 例如: Sentry, Bugsnag, Rollbar 等
            
            // 只记录服务器错误
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException 
                && $e->getStatusCode() >= 500) {
                \Log::channel('errors')->error($e->getMessage(), [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        });

        // 2. 注册默认映射
        $this->registerDefaultMappers($configure);

        // 3. 注册 API 映射
        $this->registerApiMappers($configure);

        // 4. 注册业务异常映射
        $this->registerBusinessMappers($configure);

        // 5. 注册自定义映射
        $this->registerCustomMappers($configure);
    }

    /**
     * 注册默认异常映射
     */
    protected function registerDefaultMappers(LunaExceptionConfigure $configure): void
    {
        // 方式一：注册所有默认映射
        foreach (ExceptionMappers::defaults(['debug' => false]) as $mapper) {
            $configure->wrap($mapper);
        }

        // 方式二：选择性注册（注释掉上面的代码使用这种方式）
        // $configure->wrap(ExceptionMappers::validation());
        // $configure->wrap(ExceptionMappers::authentication('/auth/login'));
        // $configure->wrap(ExceptionMappers::authorization());
        // $configure->wrap(ExceptionMappers::modelNotFound());
        // $configure->wrap(ExceptionMappers::throttle());
        
        // 自定义某个映射
        $configure->wrap(
            ExceptionMappers::queryException($this->app->hasDebugModeEnabled())
        );
    }

    /**
     * 注册 API 相关异常映射
     */
    protected function registerApiMappers(LunaExceptionConfigure $configure): void
    {
        foreach (ApiExceptionMappers::all() as $mapper) {
            $configure->wrap($mapper);
        }
    }

    /**
     * 注册业务异常映射
     */
    protected function registerBusinessMappers(LunaExceptionConfigure $configure): void
    {
        // 注册通用业务异常处理
        $configure->wrap(BusinessExceptionMappers::general());

        // 注册预定义的业务场景
        $scenarios = BusinessExceptionMappers::commonScenarios();
        $configure->wrap($scenarios['insufficient_balance']);
        $configure->wrap($scenarios['stock_shortage']);
    }

    /**
     * 注册项目特定的异常映射
     */
    protected function registerCustomMappers(LunaExceptionConfigure $configure): void
    {
        // 示例：订单相关异常
        $configure->wrap(
            LunaExceptionMapperBuilder::for(\App\Exceptions\OrderException::class)
                ->message(fn($e) => $e->getMessage())
                ->httpStatus(400)
                ->dontReport()
                ->behaviour(['action' => 'refresh_order'])
        );

        // 示例：第三方 API 异常
        $configure->wrap(
            LunaExceptionMapperBuilder::for(\App\Exceptions\ApiException::class)
                ->message('外部服务暂时不可用，请稍后重试')
                ->httpStatus(503)
                ->reportable(true)
                ->behaviour(['action' => 'retry_later'])
                ->data(fn($e) => [
                    'service' => $e->getServiceName(),
                    'retry_after' => 60,
                ])
        );

        // 示例：根据环境配置不同的处理
        if ($this->app->environment('production')) {
            // 生产环境：隐藏详细错误信息
            $configure->wrap(
                LunaExceptionMapperBuilder::for(\Exception::class)
                    ->message('系统错误，请稍后重试')
                    ->httpStatus(500)
                    ->reportable(true)
            );
        } else {
            // 开发环境：显示详细错误
            $configure->wrap(
                LunaExceptionMapperBuilder::for(\Exception::class)
                    ->message(fn($e) => $e->getMessage())
                    ->httpStatus(500)
                    ->reportable(true)
                    ->data(fn($e) => [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => collect($e->getTrace())->take(5)->toArray(),
                    ])
            );
        }
    }
}
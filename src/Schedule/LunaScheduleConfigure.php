<?php

namespace Dybasedev\LunaPrototype\Schedule;

use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;
use Dybasedev\LunaPrototype\Schedule\Models\CommandExecuteLog;
use Dybasedev\LunaPrototype\Schedule\Models\ScheduleTask;
use Dybasedev\LunaPrototype\Schedule\Models\ScheduleTaskLog;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Container\Container;

/**
 * Luna 定时任务配置类
 *
 * 负责配置 Luna 定时任务系统的各项设置，包括模型类、命令白名单等。
 * 这个类扩展了 LunaModuleConfigure，为定时任务系统提供了专门的配置选项。
 *
 * 主要配置内容：
 * - 定时任务相关的模型类
 * - 命令白名单安全控制
 * - 系统服务注册配置
 *
 * @package Dybasedev\LunaPrototype\Schedule
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class LunaScheduleConfigure extends LunaModuleConfigure
{
    /**
     * 定时任务模型类
     *
     * 指定用于存储定时任务信息的模型类。
     * 可以通过 useScheduleTaskModel 方法更换为自定义模型。
     *
     * @var class-string<ScheduleTask>
     */
    protected(set) string $scheduleTaskModel = ScheduleTask::class;

    /**
     * 定时任务日志模型类
     *
     * 指定用于存储定时任务执行日志的模型类。
     * 可以通过 useScheduleTaskLogModel 方法更换为自定义模型。
     *
     * @var class-string<ScheduleTaskLog>
     */
    protected(set) string $scheduleTaskLogModel = ScheduleTaskLog::class;

    /**
     * 命令执行日志模型类
     *
     * 指定用于存储命令执行日志的模型类。
     * 可以通过 useCommandExecuteLogModel 方法更换为自定义模型。
     *
     * @var class-string<CommandExecuteLog>
     */
    protected(set) string $commandExecuteLogModel = CommandExecuteLog::class;

    /**
     * 调度命令白名单
     *
     * 出于安全考虑，只有在此白名单中的命令才能通过调度模块执行。
     * 支持使用通配符 (*) 进行模式匹配。
     *
     * 默认包含的命令模式：
     * - 'inspirer': 特定的业务命令
     * - 'cache:*': 所有缓存相关命令
     * - 'biz:*': 所有业务相关命令
     *
     * @var array<string> 命令名称或模式数组
     */
    protected(set) array $commandWhiteList = [
        'inspirer',
        'cache:*',
        'biz:*',
    ];

    /**
     * 获取模块名称
     *
     * @return string 定时任务模块的标识名称
     */
    public function name(): string
    {
        return 'luna.schedule';
    }

    /**
     * 替换默认的定时任务模型
     *
     * 允许使用自定义的定时任务模型类替换默认的 ScheduleTask 模型。
     * 自定义模型必须继承自 ScheduleTask 或实现相同的接口。
     *
     * @param class-string<ScheduleTask> $class 自定义模型类名
     * @return $this 返回当前实例以支持链式调用
     */
    public function useScheduleTaskModel(string $class): static
    {
        $this->scheduleTaskModel = $class;
        return $this;
    }

    /**
     * 替换默认的定时任务日志模型
     *
     * 允许使用自定义的定时任务日志模型类替换默认的 ScheduleTaskLog 模型。
     * 自定义模型必须继承自 ScheduleTaskLog 或实现相同的接口。
     *
     * @param class-string<ScheduleTaskLog> $class 自定义模型类名
     * @return $this 返回当前实例以支持链式调用
     */
    public function useScheduleTaskLogModel(string $class): static
    {
        $this->scheduleTaskLogModel = $class;
        return $this;
    }

    /**
     * 替换默认的命令执行日志模型
     *
     * 允许使用自定义的命令执行日志模型类替换默认的 CommandExecuteLog 模型。
     * 自定义模型必须继承自 CommandExecuteLog 或实现相同的接口。
     *
     * @param class-string<CommandExecuteLog> $class 自定义模型类名
     * @return $this 返回当前实例以支持链式调用
     */
    public function useCommandExecuteLogModel(string $class): static
    {
        $this->commandExecuteLogModel = $class;
        return $this;
    }

    /**
     * 覆盖整个命令白名单
     *
     * 使用新的命令列表完全替换当前的命令白名单。
     * 这会清除所有现有的白名单条目。
     *
     * @param array<string> $list 新的命令白名单数组
     * @return $this 返回当前实例以支持链式调用
     */
    public function overwriteCommandWhiteList(array $list): static
    {
        $this->commandWhiteList = $list;
        return $this;
    }

    /**
     * 添加命令白名单，可以使用通配符
     *
     * 向现有的命令白名单中添加新的命令模式。
     * 支持使用通配符 (*) 进行模式匹配。
     *
     * 使用示例：
     * ```php
     * // 添加特定命令
     * $configure->addCommandWhiteList('queue:work');
     * 
     * // 添加命令模式（所有以 'biz:' 开头的命令）
     * $configure->addCommandWhiteList('biz:*');
     * ```
     *
     * @param string $command 命令名称或模式
     * @return $this 返回当前实例以支持链式调用
     */
    public function addCommandWhiteList(string $command): static
    {
        $this->commandWhiteList[] = $command;
        return $this;
    }

    /**
     * 设置命令白名单
     *
     * 设置命令白名单的便捷方法，用于链式调用。
     *
     * @param array<string> $list 命令白名单数组
     * @return $this 返回当前实例以支持链式调用
     */
    public function commandWhiteList(array $list): static
    {
        $this->commandWhiteList = $list;
        return $this;
    }

    /**
     * 获取服务提供者类名
     *
     * @return string|null 服务提供者类名
     */
    public function serviceProvider(): ?string
    {
        return LunaScheduleServiceProvider::class;
    }

    /**
     * 注册定时任务服务到容器
     *
     * 在服务容器中注册 Luna 定时任务的核心服务。
     * 这包括定时任务管理器的单例注册和相关的服务别名。
     *
     * @param Container $container Laravel 服务容器实例
     * @return void
     */
    public function register(Container $container): void
    {
        // 注册定时任务管理器单例
        $container->singleton('luna.schedule', function ($app) {
            return new LunaSchedule(
                $app->make(LunaScheduleConfigure::class),
                $app->make(Kernel::class),
                $app->make('cache.store'),
            );
        });

        // 设置服务别名
        $container->alias('luna.schedule', LunaSchedule::class);
    }

    /**
     * 启动定时任务服务
     *
     * 在 Laravel 调度系统中注册 Luna 定时任务。
     * 当 Laravel 解析 Schedule 类时，自动将 Luna 定时任务集成到调度系统中。
     *
     * @param Container $container Laravel 服务容器实例
     * @return void
     */
    public function boot(Container $container): void
    {
        $container->resolving(Schedule::class, function (Schedule $schedule) use ($container) {
            $lunaSchedule = $container->make(LunaSchedule::class);
            $lunaSchedule->schedule($schedule);
        });
    }
}
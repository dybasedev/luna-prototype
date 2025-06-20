<?php

namespace Dybasedev\LunaPrototype\Schedule;

use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;
use Dybasedev\LunaPrototype\Schedule\Models\ScheduleTask;
use Dybasedev\LunaPrototype\Schedule\Models\ScheduleTaskLog;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Container\Container;

class LunaScheduleConfigure extends LunaModuleConfigure
{
    /**
     * @var class-string<ScheduleTask>
     */
    protected(set) string $scheduleTaskModel = ScheduleTask::class;

    /**
     * @var class-string<ScheduleTaskLog>
     */
    protected(set) string $scheduleTaskLogModel = ScheduleTaskLog::class;

    /**
     * 调度命令白名单，仅存在于这个白名单的命令才可以通过调度模块执行
     * 
     * @var array|string[] 
     */
    protected(set) array $commandWhiteList = [
        'inspirer',
        'cache:*',
        'biz:*',
    ];

    public function name(): string
    {
        return 'luna.schedule';
    }

    /**
     * 替换默认的定时任务模型
     *
     * @param class-string<ScheduleTask> $class
     * @return $this
     */
    public function useScheduleTaskModel(string $class): static
    {
        $this->scheduleTaskModel = $class;
        return $this;
    }

    /**
     * 替换默认的定时任务日志模型
     *
     * @param class-string<ScheduleTaskLog> $class
     * @return $this
     */
    public function useScheduleTaskLogModel(string $class): static
    {
        $this->scheduleTaskLogModel = $class;
        return $this;
    }

    /**
     * 覆盖整个命令白名单
     *
     * @param array $list
     * @return $this
     */
    public function overwriteCommandWhiteList(array $list): static
    {
        $this->commandWhiteList = $list;
        return $this;
    }

    /**
     * 添加命令白名单，可以使用通配符
     *
     * 例如命令前缀为 'biz:'，则 'biz:*' 表示所有以 'biz:' 开头的命令
     *
     * @param string $command
     * @return $this
     */
    public function addCommandWhiteList(string $command): static
    {
        $this->commandWhiteList[] = $command;
        return $this;
    }

    public function serviceProvider(): ?string
    {
        return LunaScheduleServiceProvider::class;
    }

    public function register(Container $container): void
    {
        $container->singleton('luna.schedule', function ($app) {
            return new LunaSchedule(
                $app->make(LunaScheduleConfigure::class),
                $app->make(Kernel::class),
                $app->make('cache.store'),
            );
        });

        $container->alias('luna.schedule', LunaSchedule::class);
    }

    public function boot(Container $container): void
    {
        $container->resolving(Schedule::class, function (Schedule $schedule) use ($container) {
            $lunaSchedule = $container->make(LunaSchedule::class);
            $lunaSchedule->schedule($schedule);
        });
    }
}
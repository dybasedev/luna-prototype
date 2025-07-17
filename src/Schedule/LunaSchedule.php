<?php

namespace Dybasedev\LunaPrototype\Schedule;

use Dybasedev\LunaPrototype\Foundation\LunaModule;
use Dybasedev\LunaPrototype\Schedule\Models\ScheduleTask;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Contracts\Console\Kernel as Artisan;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

/**
 * Luna 定时任务管理器
 *
 * 这是 Luna 框架中的定时任务管理系统，提供了灵活的定时任务调度功能。
 * 它扩展了 Laravel 的任务调度功能，添加了数据库管理、日志记录、命令过滤等特性。
 *
 * 主要功能：
 * - 数据库驱动的定时任务管理
 * - 任务执行日志记录
 * - 命令白名单过滤
 * - 任务重叠检测
 * - 维护模式支持
 * - 单服务器执行控制
 * - 后台运行支持
 *
 * 系统特性：
 * - 支持动态任务配置
 * - 提供完整的任务监控
 * - 集成缓存提高性能
 * - 支持时区配置
 * - 异常处理和日志记录
 *
 * @package Dybasedev\LunaPrototype\Schedule
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class LunaSchedule extends LunaModule
{
    /**
     * 定时任务管理器构造函数
     *
     * @param LunaScheduleConfigure $configure 定时任务配置对象
     * @param Artisan $artisan Laravel Artisan 内核
     * @param Cache $cache 缓存接口实例
     */
    public function __construct(
        protected(set) LunaScheduleConfigure $configure,
        protected(set) Artisan $artisan,
        protected Cache $cache
    ) {

    }

    /**
     * 获取所有激活的定时任务
     *
     * 从数据库获取所有状态为激活的定时任务，并使用缓存提高性能。
     * 缓存键为 'schedule-task:all-active'，永久缓存直到手动清除。
     *
     * @return array<ScheduleTask> 激活的定时任务数组
     */
    public function getActiveTasks(): array
    {
        return $this->cache->rememberForever('schedule-task:all-active', function () {
            return $this->configure->scheduleTaskModel::query()->active()->get()->all();
        });
    }

    /**
     * 检查定时任务系统是否启用
     *
     * 通过检查数据库表是否存在来判断定时任务系统是否已启用。
     * 使用缓存避免重复的数据库查询。
     *
     * @return bool 如果定时任务系统启用返回 true，否则返回 false
     */
    protected function isEnabled(): bool
    {
        try {
            // 首先检查缓存
            if ($this->cache->get('schedule-task:enabled')) {
                return true;
            }

            // 检查数据库表是否存在
            if (Schema::hasTable('luna_schedule_tasks')) {
                $this->cache->forever('schedule-task:enabled', true);
                return true;
            }

            return false;
        } catch (Throwable $exception) {
            Log::warning('LunaSchedule: Failed to check schedule task enabled status.', [
                'exception' => $exception->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 注册 Laravel 定时任务
     *
     * 将数据库中的定时任务注册到 Laravel 的任务调度系统中。
     * 为每个任务配置执行参数、日志记录、错误处理等。
     *
     * @param Schedule $schedule Laravel 调度对象
     * @return void
     */
    protected function registerLaravelSchedules(Schedule $schedule): void
    {
        $tasks = $this->getActiveTasks();

        collect($tasks)->each(function (ScheduleTask $task) use ($schedule) {
            $event = $schedule->command($task->command, $task->compileParameters(true));
            $scheduleTraceLog = null;
            $start = null;

            $event->cron($task->expression)
                ->name($task->description)
                ->timezone($task->timezone)
                ->before(function () use ($task, $event, &$scheduleTraceLog, &$start) {
                    // 任务开始前，首先记录任务日志记录
                    try {
                        $scheduleTraceLog = $this->configure->scheduleTaskLogModel::query()->create([
                            'task_id' => $task->id,
                            'output' => '',
                            'ran_at' => Carbon::now(),
                        ]);
                        $start = microtime(true);
                    } catch (Throwable $exception) {
                        Log::error('schedule record trace log error', ['exception' => $exception]);
                    }
                })
                ->thenWithOutput(function ($output) use (&$start, &$scheduleTraceLog) {
                    // 任务结束后记录输出信息
                    if ($scheduleTraceLog && $start) {
                        try {
                            $scheduleTraceLog->update([
                                'output' => $output,
                            ]);
                        } catch (Throwable $exception) {
                            Log::error('schedule record trace log error', ['exception' => $exception]);
                        }
                    }
                })
                ->onSuccess(function () use (&$start, &$scheduleTraceLog) {
                    // 任务成功完成时记录成功状态和执行时间
                    if ($scheduleTraceLog && $start) {
                        try {
                            $scheduleTraceLog->update([
                                'status' => 1,
                                'duration' => microtime(true) - $start,
                                'end_at' => Carbon::now(),
                            ]);
                        } catch (Throwable $exception) {
                            Log::error('schedule record trace log error', ['exception' => $exception]);
                        }
                    }
                })
                ->onFailure(function () use (&$start, &$scheduleTraceLog) {
                    // 任务失败时记录失败状态和执行时间
                    if ($scheduleTraceLog && $start) {
                        try {
                            $scheduleTraceLog->update([
                                'status' => 0,
                                'duration' => microtime(true) - $start,
                                'end_at' => Carbon::now(),
                            ]);
                        } catch (Throwable $exception) {
                            Log::error('schedule record trace log error', ['exception' => $exception]);
                        }
                    }
                });

            // 配置任务执行选项
            if ($task->dontOverlap()) {
                $event->withoutOverlapping();
            }

            if ($task->runInMaintenance()) {
                $event->evenInMaintenanceMode();
            }

            if ($task->runOnOneServer() && in_array(
                    config('cache.default'),
                    ['memcached', 'redis', 'database', 'dynamodb']
                )) {
                $event->onOneServer();
            }

            if ($task->runInBackground()) {
                $event->runInBackground();
            }
        });
    }

    /**
     * 调度定时任务
     *
     * 这是主要的调度方法，被 Laravel 调度系统调用。
     * 如果定时任务系统已启用，则注册所有激活的任务。
     *
     * @param Schedule $schedule Laravel 调度对象
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        if ($this->isEnabled()) {
            $this->registerLaravelSchedules($schedule);
        }
    }

    /**
     * 获取可用的命令列表
     *
     * 根据命令白名单过滤所有可用的 Artisan 命令，并返回详细信息。
     * 只有在白名单中的命令才能被用于创建定时任务。
     *
     * @return array 可用命令的详细信息数组，包含命令名、描述、参数和选项
     */
    public function availableCommands(): array
    {
        /** @var Collection $collection */
        $collection = collect($this->artisan->all())
            ->filter(function (Command $command) {
                // 根据白名单过滤命令
                return count(
                        array_filter(
                            $this->configure->commandWhiteList,
                            fn($match) => Str::of($command->getName())->is($match)
                        )
                    ) > 0;
            })
            ->map(function (Command $command) {
                // 提取命令参数信息
                $arguments = array_map(fn (InputArgument $argument) => [
                    'name' => $argument->getName(),
                    'description' => $argument->getDescription(),
                    'is_required' => $argument->isRequired(),
                    'default' => $argument->getDefault(),
                    'is_array' => $argument->isArray(),
                ], $command->getDefinition()->getArguments());

                // 提取命令选项信息
                $options = array_map(fn (InputOption $option) => [
                    'name' => $option->getName(),
                    'description' => $option->getDescription(),
                    'shortcut' => $option->getShortcut(),
                    'is_value_required' => $option->isValueRequired(),
                    'is_value_optional' => $option->isValueOptional(),
                    'default' => $option->getDefault(),
                    'is_array' => $option->isArray(),
                ], $command->getDefinition()->getOptions());

                return [
                    'command' => $command->getName(),
                    'description' => $command->getDescription(),
                    'options' => $options,
                    'arguments' => $arguments,
                ];
            });

        return $collection->keyBy('command')->all();
    }
}
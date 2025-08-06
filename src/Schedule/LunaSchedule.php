<?php

namespace Dybasedev\LunaPrototype\Schedule;

use Dybasedev\LunaPrototype\Foundation\LunaModule;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Schedule\Models\ScheduleTask;
use Dybasedev\LunaPrototype\Schedule\Models\CommandExecuteLog;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Contracts\Console\Kernel as Artisan;
use Illuminate\Support\Str;
use InvalidArgumentException;
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
     * 创建新的调度任务
     *
     * @param string $name 任务唯一名称
     * @param string $command 要执行的命令
     * @param string $expression Cron 表达式
     * @param array $options 其他选项配置
     * @return ScheduleTask 创建的任务实例
     */
    public function createTask(string $name, string $command, string $expression, array $options = []): ScheduleTask
    {
        // 验证命令是否在白名单中
        if (!$this->isCommandAllowed($command)) {
            throw new InvalidArgumentException("Command '{$command}' is not in the whitelist.");
        }

        $data = [
            'name' => $name,
            'display_name' => $options['display_name'] ?? $name,
            'description' => $options['description'] ?? '',
            'expression' => $expression,
            'expression_type' => $options['expression_type'] ?? 1,
            'timezone' => $options['timezone'] ?? config('app.timezone'),
            'command' => $command,
            'payload' => array_merge([
                'parameters' => $options['parameters'] ?? '',
                'priority' => $options['priority'] ?? 'normal',
                'max_retries' => $options['max_retries'] ?? 0,
                'retry_delay' => $options['retry_delay'] ?? 300,
                'dont_overlap' => $options['dont_overlap'] ?? false,
                'run_in_maintenance' => $options['run_in_maintenance'] ?? false,
                'run_in_background' => $options['run_in_background'] ?? false,
                'run_on_one_server' => $options['run_on_one_server'] ?? true,
            ], $options['payload'] ?? []),
            'enabled' => $options['enabled'] ?? true,
        ];

        $task = $this->configure->scheduleTaskModel::create($data);
        
        // 清除缓存
        $this->cache->forget('schedule-task:all-active');
        
        return $task;
    }

    /**
     * 更新调度任务
     *
     * @param ScheduleTask|string $task 任务实例或任务名称
     * @param array $data 要更新的数据
     * @return ScheduleTask 更新后的任务实例
     */
    public function updateTask(ScheduleTask|string $task, array $data): ScheduleTask
    {
        if (is_string($task)) {
            $task = $this->configure->scheduleTaskModel::where('name', $task)->firstOrFail();
        }

        // 如果更新命令，需要验证白名单
        if (isset($data['command']) && !$this->isCommandAllowed($data['command'])) {
            throw new InvalidArgumentException("Command '{$data['command']}' is not in the whitelist.");
        }

        // 合并 payload 数据
        if (isset($data['payload'])) {
            $data['payload'] = array_merge($task->payload ?? [], $data['payload']);
        }

        $task->update($data);
        
        // 清除缓存
        $this->cache->forget('schedule-task:all-active');
        
        return $task;
    }

    /**
     * 删除调度任务
     *
     * @param ScheduleTask|string $task 任务实例或任务名称
     * @return bool 是否删除成功
     */
    public function deleteTask(ScheduleTask|string $task): bool
    {
        if (is_string($task)) {
            $task = $this->configure->scheduleTaskModel::where('name', $task)->firstOrFail();
        }

        $result = $task->delete();
        
        // 清除缓存
        $this->cache->forget('schedule-task:all-active');
        
        return $result;
    }

    /**
     * 手动执行任务
     *
     * @param ScheduleTask|string $task 任务实例或任务名称
     * @return Models\ScheduleTaskLog 执行日志
     */
    public function runTask(ScheduleTask|string $task): Models\ScheduleTaskLog
    {
        if (is_string($task)) {
            $task = $this->configure->scheduleTaskModel::where('name', $task)->firstOrFail();
        }

        $log = $this->configure->scheduleTaskLogModel::create([
            'task_id' => $task->id,
            'output' => '',
            'ran_at' => Carbon::now(),
            'status' => 0, // 初始状态
        ]);

        $start = microtime(true);
        $output = '';

        try {
            // 构建命令参数
            $parameters = $task->compileParameters(true);
            
            // 执行命令
            $exitCode = $this->artisan->call($task->command, $parameters);
            
            // 获取输出（处理不同的 Artisan 实现）
            if (method_exists($this->artisan, 'output')) {
                $output = $this->artisan->output();
            }
            
            $log->update([
                'status' => $exitCode === 0 ? 1 : 0,
                'output' => $output,
                'duration' => microtime(true) - $start,
                'end_at' => Carbon::now(),
            ]);
            
            // 处理重试逻辑
            if ($exitCode !== 0 && $task->payload['max_retries'] > 0) {
                $this->scheduleRetry($task, $log);
            }
            
        } catch (Throwable $e) {
            $log->update([
                'status' => 0,
                'output' => $e->getMessage() . "\n" . $e->getTraceAsString(),
                'duration' => microtime(true) - $start,
                'end_at' => Carbon::now(),
            ]);
            
            Log::error('Schedule task execution failed', [
                'task' => $task->name,
                'exception' => $e->getMessage(),
            ]);
        }

        return $log;
    }

    /**
     * 获取任务实例
     *
     * @param string $name 任务名称
     * @return ScheduleTask|null
     */
    public function getTask(string $name): ?ScheduleTask
    {
        return $this->configure->scheduleTaskModel::where('name', $name)->first();
    }

    /**
     * 获取所有任务
     *
     * @param bool $onlyActive 是否只获取激活的任务
     * @return Collection
     */
    public function getTasks(bool $onlyActive = false): Collection
    {
        $query = $this->configure->scheduleTaskModel::query();
        
        if ($onlyActive) {
            $query->active();
        }
        
        return $query->get();
    }

    /**
     * 启用或禁用任务
     *
     * @param ScheduleTask|string $task 任务实例或任务名称
     * @param bool $enabled 是否启用
     * @return ScheduleTask
     */
    public function toggleTask(ScheduleTask|string $task, bool $enabled): ScheduleTask
    {
        if (is_string($task)) {
            $task = $this->configure->scheduleTaskModel::where('name', $task)->firstOrFail();
        }

        $task->update(['enabled' => $enabled]);
        
        // 清除缓存
        $this->cache->forget('schedule-task:all-active');
        
        return $task;
    }

    /**
     * 检查命令是否在白名单中
     *
     * @param string $command 命令名称
     * @return bool
     */
    protected function isCommandAllowed(string $command): bool
    {
        // 提取基础命令名（去除参数）
        $baseCommand = explode(' ', $command)[0];
        
        return count(
            array_filter(
                $this->configure->commandWhiteList,
                fn($match) => Str::of($baseCommand)->is($match)
            )
        ) > 0;
    }

    /**
     * 安排任务重试
     *
     * @param ScheduleTask $task 任务实例
     * @param Models\ScheduleTaskLog $failedLog 失败的日志
     * @return void
     */
    protected function scheduleRetry(ScheduleTask $task, Models\ScheduleTaskLog $failedLog): void
    {
        $retryCount = $failedLog->retry_count ?? 0;
        
        if ($retryCount < $task->payload['max_retries']) {
            // 创建重试任务
            $retryDelay = $task->payload['retry_delay'] ?? 300; // 默认5分钟后重试
            
            // 这里可以实现延迟队列或其他重试机制
            // 为简化，我们记录重试信息
            Log::info('Schedule task retry scheduled', [
                'task' => $task->name,
                'retry_count' => $retryCount + 1,
                'retry_after' => $retryDelay,
            ]);
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

    /**
     * 执行命令并记录日志
     *
     * @param string $command 命令名称
     * @param array $parameters 命令参数
     * @param SessionHolder|null $operator 操作者（必须实现 SessionHolder 接口）
     * @param string $comment 备注
     * @return CommandExecuteLog
     * @throws Throwable
     */
    public function executeCommand(string $command, array $parameters = [], ?SessionHolder $operator = null, string $comment = ''): CommandExecuteLog
    {
        // 验证命令是否在白名单中
        if (!$this->isCommandAllowed($command)) {
            throw new InvalidArgumentException("Command '{$command}' is not in the whitelist.");
        }

        // 创建日志记录
        $log = CommandExecuteLog::createLog($command, $operator, $comment);
        $log->update(['payload' => $parameters]);

        $start = microtime(true);
        $output = '';

        try {
            // 执行命令
            $exitCode = $this->artisan->call($command, $parameters);
            
            // 获取输出（处理不同的 Artisan 实现）
            if (method_exists($this->artisan, 'output')) {
                $output = $this->artisan->output();
            }
            
            $log->update([
                'status' => $exitCode === 0 ? 1 : 0,
                'output' => $output,
                'duration' => microtime(true) - $start,
                'end_at' => Carbon::now(),
            ]);
            
        } catch (Throwable $e) {
            $log->update([
                'status' => 0,
                'output' => $e->getMessage() . "\n" . $e->getTraceAsString(),
                'duration' => microtime(true) - $start,
                'end_at' => Carbon::now(),
            ]);
            
            Log::error('Command execution failed', [
                'command' => $command,
                'exception' => $e->getMessage(),
            ]);
            
            throw $e;
        }

        return $log;
    }

    /**
     * 获取任务执行统计
     *
     * @param ScheduleTask|string|null $task 任务实例、任务名称或null（获取所有）
     * @param int $days 统计天数
     * @return array
     */
    public function getTaskStatistics(ScheduleTask|string|null $task = null, int $days = 7): array
    {
        $query = $this->configure->scheduleTaskLogModel::query()
            ->where('ran_at', '>=', Carbon::now()->subDays($days));

        if ($task !== null) {
            if (is_string($task)) {
                $task = $this->configure->scheduleTaskModel::where('name', $task)->firstOrFail();
            }
            $query->where('task_id', $task->id);
        }

        $logs = $query->get();

        return [
            'total_runs' => $logs->count(),
            'successful_runs' => $logs->where('status', 1)->count(),
            'failed_runs' => $logs->where('status', 0)->count(),
            'success_rate' => $logs->count() > 0 ? round($logs->where('status', 1)->count() / $logs->count() * 100, 2) : 0,
            'average_duration' => $logs->avg('duration') ?? 0,
            'total_duration' => $logs->sum('duration') ?? 0,
            'last_run' => $logs->sortByDesc('ran_at')->first(),
        ];
    }

    /**
     * 清理旧的执行日志
     *
     * @param int $days 保留天数
     * @return int 删除的记录数
     */
    public function cleanOldLogs(int $days = 30): int
    {
        $deletedTaskLogs = $this->configure->scheduleTaskLogModel::query()
            ->where('created_at', '<', Carbon::now()->subDays($days))
            ->delete();

        $deletedCommandLogs = $this->configure->commandExecuteLogModel::query()
            ->where('created_at', '<', Carbon::now()->subDays($days))
            ->delete();

        return $deletedTaskLogs + $deletedCommandLogs;
    }
}
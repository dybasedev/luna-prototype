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

class LunaSchedule extends LunaModule
{
    public function __construct(
        protected(set) LunaScheduleConfigure $configure,
        protected(set) Artisan $artisan,
        protected Cache $cache
    ) {

    }

    /**
     * 获取所有激活的定时任务
     *
     * @return array
     */
    public function getActiveTasks(): array
    {
        return $this->cache->rememberForever('schedule-task:all-active', function () {
            return $this->configure->scheduleTaskModel::query()->active()->get()->all();
        });
    }

    protected function isEnabled(): bool
    {
        try {
            if ($this->cache->get('schedule-task:enabled')) {
                return true;
            }

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
                    // 任务结束后进行记录
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

    public function schedule(Schedule $schedule): void
    {
        if ($this->isEnabled()) {
            $this->registerLaravelSchedules($schedule);
        }
    }

    public function availableCommands(): array
    {
        /** @var Collection $collection */
        $collection = collect($this->artisan->all())
            ->filter(function (Command $command) {
                return count(
                        array_filter(
                            $this->configure->commandWhiteList,
                            fn($match) => Str::of($command->getName())->is($match)
                        )
                    ) > 0;
            })
            ->map(function (Command $command) {
                $arguments = array_map(fn (InputArgument $argument) => [
                    'name' => $argument->getName(),
                    'description' => $argument->getDescription(),
                    'is_required' => $argument->isRequired(),
                    'default' => $argument->getDefault(),
                    'is_array' => $argument->isArray(),
                ], $command->getDefinition()->getArguments());

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
<?php

namespace Dybasedev\LunaPrototype\Schedule\Models;

use Dybasedev\LunaPrototype\Schedule\LunaScheduleConfigure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 *
 *
 * @property int $id
 * @property string $name 任务名
 * @property string $display_name 显示名
 * @property string $description 描述
 * @property string $expression 表达式
 * @property int $expression_type 表达式类型
 * @property string $timezone 时区
 * @property string $command 命令
 * @property array<array-key, mixed> $payload 配置
 * @property int $status 状态
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read ScheduleTaskLog|null $latestLog
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ScheduleTaskLog> $logs
 * @property-read int|null $logs_count
 * @method static Builder<static>|ScheduleTask active()
 * @method static Builder<static>|ScheduleTask newModelQuery()
 * @method static Builder<static>|ScheduleTask newQuery()
 * @method static Builder<static>|ScheduleTask query()
 * @method static Builder<static>|ScheduleTask whereCommand($value)
 * @method static Builder<static>|ScheduleTask whereCreatedAt($value)
 * @method static Builder<static>|ScheduleTask whereDescription($value)
 * @method static Builder<static>|ScheduleTask whereDisplayName($value)
 * @method static Builder<static>|ScheduleTask whereExpression($value)
 * @method static Builder<static>|ScheduleTask whereExpressionType($value)
 * @method static Builder<static>|ScheduleTask whereId($value)
 * @method static Builder<static>|ScheduleTask whereName($value)
 * @method static Builder<static>|ScheduleTask wherePayload($value)
 * @method static Builder<static>|ScheduleTask whereStatus($value)
 * @method static Builder<static>|ScheduleTask whereTimezone($value)
 * @method static Builder<static>|ScheduleTask whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ScheduleTask extends Model
{
    public function scopeActive(Builder $query): void
    {
        $query->where('status', true);
    }

    public function compileParameters(bool $console = false): array
    {
        if (isset($this->payload['parameters'])) {
            $regex = '/(?=\S)[^\'"\s]*(?:\'[^\']*\'[^\'"\s]*|"[^"]*"[^\'"\s]*)*/';
            preg_match_all($regex, $this->payload['parameters'], $matches, PREG_SET_ORDER, 0);

            $argument_index = 0;

            $duplicate_parameter_index = function (array $carry, array $param, string $trimmed_param) {
                if (!isset($carry[$param[0]])) {
                    $carry[$param[0]] = $trimmed_param;
                } else {
                    if (!is_array($carry[$param[0]])) {
                        $carry[$param[0]] = [$carry[$param[0]]];
                    }
                    $carry[$param[0]][] = $trimmed_param;
                }

                return $carry;
            };

            return collect($matches)->reduce(function ($carry, $parameter) use (
                $console,
                &$argument_index,
                $duplicate_parameter_index
            ) {
                $param = explode('=', $parameter[0], 2);

                if (count($param) > 1) {
                    $trimmed_param = trim(trim($param[1], '"'), "'");
                    if ($console) {
                        if (Str::startsWith($param[0], ['--', '-'])) {
                            $carry = $duplicate_parameter_index($carry, $param, $trimmed_param);
                        } else {
                            $carry[$argument_index++] = $trimmed_param;
                        }

                        return $carry;
                    }

                    return $duplicate_parameter_index($carry, $param, $trimmed_param);
                }

                Str::startsWith($param[0], ['--', '-']) && !$console ?
                    $carry[$param[0]] = true :
                    $carry[$argument_index++] = $param[0];

                return $carry;
            }, []);
        }

        return [];
    }

    /**
     * @throws BindingResolutionException
     */
    public function logs(): HasMany
    {
        return $this->hasMany(luna_module_configure(LunaScheduleConfigure::class)->scheduleTaskLogModel, 'task_id', 'id');
    }

    /**
     * @throws BindingResolutionException
     */
    public function latestLog(): HasOne
    {
        return $this->logs()->one()->latestOfMany();
    }

    public function dontOverlap(): bool
    {
        return $this->payload['dont_overlap'] ?? false;
    }

    public function runInMaintenance(): bool
    {
        return $this->payload['run_in_maintenance'] ?? false;
    }

    public function runInBackground(): bool
    {
        return $this->payload['run_in_background'] ?? false;
    }

    public function runOnOneServer(): bool
    {
        return $this->payload['run_on_one_server'] ?? true;
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
<?php

namespace Dybasedev\LunaPrototype\Schedule\Models;

use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property int $operator_type 操作者类型
 * @property int $operator_id 操作者ID
 * @property string $command 命令
 * @property array<array-key, mixed> $payload 配置
 * @property string $comment 备注
 * @property \Illuminate\Support\Carbon $ran_at 开始时间
 * @property \Illuminate\Support\Carbon $end_at 结束时间
 * @property string $duration 持续时间
 * @property int $status 状态
 * @property string $output 输出
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommandExecuteLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommandExecuteLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommandExecuteLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommandExecuteLog whereCommand($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommandExecuteLog whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommandExecuteLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommandExecuteLog whereDuration($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommandExecuteLog whereEndAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommandExecuteLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommandExecuteLog whereOperatorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommandExecuteLog whereOperatorType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommandExecuteLog whereOutput($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommandExecuteLog wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommandExecuteLog whereRanAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommandExecuteLog whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommandExecuteLog whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CommandExecuteLog extends Model
{
    protected $table = 'luna_command_execute_logs';

    protected function casts(): array
    {
        return [
            'ran_at' => 'datetime',
            'end_at' => 'datetime',
            'payload' => 'array',
        ];
    }
}
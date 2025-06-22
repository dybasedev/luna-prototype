<?php

namespace Dybasedev\LunaPrototype\Schedule\Models;

use Dybasedev\LunaPrototype\Schedule\LunaScheduleConfigure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
*
*
* @property int $id
* @property int $task_id 任务ID
* @property \Illuminate\Support\Carbon $ran_at 开始时间
* @property \Illuminate\Support\Carbon $end_at 结束时间
* @property string $duration 持续时间
* @property int $status 状态
* @property string $output 输出
* @property \Illuminate\Support\Carbon|null $created_at
* @property \Illuminate\Support\Carbon|null $updated_at
* @property-read ScheduleTask|null $task
* @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleTaskLog newModelQuery()
* @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleTaskLog newQuery()
* @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleTaskLog query()
* @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleTaskLog whereCreatedAt($value)
* @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleTaskLog whereDuration($value)
* @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleTaskLog whereEndAt($value)
* @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleTaskLog whereId($value)
* @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleTaskLog whereOutput($value)
* @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleTaskLog whereRanAt($value)
* @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleTaskLog whereStatus($value)
* @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleTaskLog whereTaskId($value)
* @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleTaskLog whereUpdatedAt($value)
* @mixin \Eloquent
*/
class ScheduleTaskLog extends Model
{
    protected $table = 'luna_schedule_task_logs';

    protected $guarded = [];

    /**
     * @throws BindingResolutionException
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(luna_module_configure(LunaScheduleConfigure::class)->scheduleTaskModel, 'task_id',
            'id');
    }

    protected function casts(): array
    {
        return [
            'ran_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }
}
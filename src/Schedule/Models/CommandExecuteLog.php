<?php

namespace Dybasedev\LunaPrototype\Schedule\Models;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
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
    
    protected $fillable = [
        'operator_type',
        'operator_id',
        'command',
        'payload',
        'comment',
        'ran_at',
        'end_at',
        'duration',
        'status',
        'output',
    ];


    /**
     * 获取操作者
     * 
     * 由于 operator_type 存储的是 hash_code 值（整数），
     * 不能直接使用 morphTo，需要手动处理关系
     * 
     * @return Model|null
     */
    public function getOperator(): ?Model
    {
        // 对于系统操作，没有具体的操作者
        if ($this->operator_id === 0) {
            return null;
        }
        
        // 这里需要根据实际使用情况，通过 operator_type 的 hash_code 值
        // 来确定对应的模型类，然后查询对应的记录
        // 实际项目中应该维护一个 hash_code 到类名的映射表
        return null;
    }

    /**
     * 判断命令是否执行成功
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->status === 1;
    }


    /**
     * 创建命令执行日志
     *
     * @param string $command 命令
     * @param SessionHolder|null $operator 操作者（必须实现 SessionHolder 接口）
     * @param string $comment 备注
     * @return static
     */
    public static function createLog(string $command, ?SessionHolder $operator = null, string $comment = ''): static
    {
        $operatorType = hash_code('system'); // 默认为系统类型
        $operatorId = 0;

        if ($operator) {
            $operatorType = $operator->getOperatorType();
            $operatorId = $operator->getOperatorId();
        }

        return static::create([
            'operator_type' => $operatorType,
            'operator_id' => $operatorId,
            'command' => $command,
            'payload' => [],
            'comment' => $comment,
            'ran_at' => now(),
            'end_at' => null,
            'duration' => 0,
            'status' => 0,
            'output' => '',
        ]);
    }

    protected function casts(): array
    {
        return [
            'ran_at' => 'datetime',
            'end_at' => 'datetime',
            'payload' => 'array',
        ];
    }
}
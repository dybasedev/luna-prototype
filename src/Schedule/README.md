# Schedule 模块

Schedule 模块提供了灵活的任务调度和后台作业管理功能，支持定时任务、命令执行日志记录和任务状态监控。

## 功能特性

- **定时任务管理**：创建和管理各种定时任务
- **命令执行日志**：记录所有命令的执行历史和结果
- **任务状态监控**：实时跟踪任务执行状态
- **失败重试机制**：自动重试失败的任务
- **任务优先级**：支持不同优先级的任务调度
- **灵活的调度规则**：支持 Cron 表达式和其他调度规则

## 核心概念

### 调度任务（ScheduleTask）

调度任务是计划在特定时间或按特定规则执行的任务。

### 任务日志（ScheduleTaskLog）

记录每次任务执行的详细信息，包括开始时间、结束时间、执行结果等。

### 命令执行日志（CommandExecuteLog）

记录通过调度系统执行的命令的详细日志。

## 快速开始

### 创建调度任务

```php
use Dybasedev\LunaPrototype\Schedule\LunaSchedule;

$schedule = app(LunaSchedule::class);

// 创建一个每日执行的任务
$task = $schedule->createTask(
    'daily-backup',
    'backup:database',
    '0 2 * * *'  // 每天凌晨2点执行
);
```

### 执行任务

```php
// 手动执行任务
$result = $schedule->runTask($task);

// 检查执行结果
if ($result->isSuccess()) {
    echo "任务执行成功";
} else {
    echo "任务执行失败: " . $result->getError();
}
```

### 查看任务日志

```php
// 获取任务的执行日志
$logs = $task->logs()
    ->latest()
    ->limit(10)
    ->get();

foreach ($logs as $log) {
    echo sprintf(
        "%s - %s (%s秒)\n",
        $log->started_at,
        $log->status,
        $log->duration
    );
}
```

## 高级用法

### 任务优先级

```php
// 创建高优先级任务
$task = $schedule->createTask(
    'urgent-task',
    'process:urgent-orders',
    '*/5 * * * *',  // 每5分钟执行
    ['priority' => 'high']
);
```

### 任务重试

```php
// 配置任务重试
$task = $schedule->createTask(
    'retry-task',
    'sync:external-api',
    '0 * * * *',
    [
        'max_retries' => 3,
        'retry_delay' => 300  // 5分钟后重试
    ]
);
```

### 任务链

```php
// 创建任务链
$schedule->chain([
    'backup:database',
    'backup:files',
    'backup:upload'
])->daily()->at('02:00');
```

## 模型关系

- **ScheduleTask**: 调度任务主表
- **ScheduleTaskLog**: 任务执行日志
- **CommandExecuteLog**: 命令执行日志

## 配置选项

```php
use Dybasedev\LunaPrototype\Schedule\LunaScheduleConfigure;

$configure = LunaScheduleConfigure::create()
    ->maxConcurrentTasks(10)  // 最大并发任务数
    ->defaultTimeout(3600)    // 默认超时时间（秒）
    ->enableLogging(true)     // 启用日志记录
    ->build();
```

## 事件

Schedule 模块会触发以下事件：

- `schedule.task.starting`: 任务开始执行前
- `schedule.task.completed`: 任务执行完成后
- `schedule.task.failed`: 任务执行失败时

## 最佳实践

1. **合理设置任务执行时间**：避免在业务高峰期执行耗时任务
2. **监控任务执行状态**：定期检查失败的任务并及时处理
3. **使用任务优先级**：确保重要任务优先执行
4. **设置合理的超时时间**：防止任务长时间占用资源
5. **记录详细日志**：便于问题排查和性能优化
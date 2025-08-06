# Schedule 模块

Schedule 模块提供了灵活的任务调度和后台作业管理功能，支持定时任务、命令执行日志记录和任务状态监控。

## 功能特性

- **定时任务管理**：创建、更新、删除和管理各种定时任务
- **命令执行日志**：记录所有命令的执行历史和结果，支持操作者追踪
- **任务状态监控**：实时跟踪任务执行状态和执行统计
- **失败重试机制**：自动重试失败的任务，支持自定义重试次数和延迟
- **任务优先级**：支持不同优先级的任务调度
- **灵活的调度规则**：支持 Cron 表达式和链式调度配置
- **命令白名单**：安全控制，只允许执行白名单中的命令
- **手动执行**：支持手动触发任务执行并记录日志
- **统计分析**：提供任务执行统计和成功率分析
- **日志清理**：自动清理过期的执行日志

## 核心概念

### 调度任务（ScheduleTask）

调度任务是计划在特定时间或按特定规则执行的任务。

### 任务日志（ScheduleTaskLog）

记录每次任务执行的详细信息，包括开始时间、结束时间、执行结果等。

### 命令执行日志（CommandExecuteLog）

记录通过调度系统执行的命令的详细日志。

## 安装配置

### 1. 注册模块

在 `AppServiceProvider` 中注册 Schedule 模块：

```php
public function register(): void
{
    parent::register();
    
    $this->registerModule(
        LunaScheduleConfigure::create()
            ->addCommand('backup:database')
            ->addCommand('cache:clear')
            ->addCommand('queue:work')
            ->build()
    );
}
```

### 2. 发布并运行迁移

```bash
# 发布迁移文件到项目
php artisan vendor:publish --provider="Dybasedev\LunaPrototype\Schedule\LunaScheduleServiceProvider" --tag=migrations

# 运行迁移
php artisan migrate
```

这会创建以下数据表：
- `luna_schedule_tasks` - 调度任务表
- `luna_schedule_task_logs` - 任务执行日志表
- `luna_command_execute_logs` - 命令执行日志表

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
    [
        'priority' => 'high',
        'description' => '处理紧急订单'
    ]
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
        'retry_delay' => 300,  // 5分钟后重试
        'description' => '同步外部API数据'
    ]
);
```


### 任务管理

```php
// 获取任务
$task = $schedule->getTask('daily-backup');

// 更新任务
$schedule->updateTask($task, [
    'expression' => '0 3 * * *',  // 改为凌晨3点执行
    'payload' => [
        'dont_overlap' => true
    ]
]);

// 启用/禁用任务
$schedule->toggleTask('daily-backup', false);  // 禁用
$schedule->toggleTask('daily-backup', true);   // 启用

// 删除任务
$schedule->deleteTask('old-task');
```

### 执行命令并记录日志

```php
// 执行命令并记录到命令执行日志
$log = $schedule->executeCommand(
    'cache:clear',
    ['--force' => true],  // 参数
    null,  // 操作者，null 表示系统
    '系统自动清理缓存'  // 备注
);

// 带操作者信息执行（操作者必须实现 SessionHolder 接口）
$user = User::find(1); // User 模型必须实现 SessionHolder 接口
$log = $schedule->executeCommand(
    'cache:clear',
    [],  // 参数
    $user,  // 操作者
    '手动清理缓存'  // 备注
);

if ($log->isSuccess()) {
    echo "命令执行成功";
}
```

注意：操作者类型通过 Foundation 组件的 SessionHolder 机制管理，使用 `hash_code()` 函数生成类型 ID。

### 任务统计

```php
// 获取任务执行统计（最近7天）
$stats = $schedule->getTaskStatistics('daily-backup');

echo "总执行次数: " . $stats['total_runs'] . "\n";
echo "成功率: " . $stats['success_rate'] . "%\n";
echo "平均执行时间: " . $stats['average_duration'] . "秒\n";

// 获取所有任务的统计
$allStats = $schedule->getTaskStatistics(null, 30);  // 最近30天
```

### 日志清理

```php
// 清理30天前的日志
$deleted = $schedule->cleanOldLogs(30);
echo "已清理 {$deleted} 条旧日志";
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
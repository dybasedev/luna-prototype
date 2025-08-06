<?php

use Dybasedev\LunaPrototype\Schedule\LunaSchedule;
use Dybasedev\LunaPrototype\Schedule\LunaScheduleConfigure;
use Dybasedev\LunaPrototype\Schedule\Models\ScheduleTask;
use Dybasedev\LunaPrototype\Schedule\Models\ScheduleTaskLog;
use Dybasedev\LunaPrototype\Schedule\Models\CommandExecuteLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Contracts\Console\Kernel;

uses(RefreshDatabase::class);

beforeEach(function () {
    // 创建配置实例
    $configure = new LunaScheduleConfigure();
    $configure->commandWhiteList(['test:*', 'cache:*', 'queue:*']);
    
    // 注册配置到容器
    app()->singleton(LunaScheduleConfigure::class, fn() => $configure);
    
    // 注册服务
    $configure->register(app());
    
    $this->schedule = app('luna.schedule');
});

afterEach(function () {
    if (class_exists('Mockery')) {
        \Mockery::close();
    }
});

it('可以创建新任务', function () {
    $task = $this->schedule->createTask(
        'test-task',
        'test:command',
        '0 * * * *',
        [
            'description' => '测试任务',
            'timezone' => 'Asia/Shanghai',
            'priority' => 'high',
            'max_retries' => 3,
            'retry_delay' => 600,
        ]
    );
    
    expect($task)->toBeInstanceOf(ScheduleTask::class);
    expect($task->name)->toBe('test-task');
    expect($task->command)->toBe('test:command');
    expect($task->expression)->toBe('0 * * * *');
    expect($task->description)->toBe('测试任务');
    expect($task->timezone)->toBe('Asia/Shanghai');
    expect($task->payload['priority'])->toBe('high');
    expect($task->payload['max_retries'])->toBe(3);
    expect($task->payload['retry_delay'])->toBe(600);
    expect($task->enabled)->toBeTrue();
});

it('验证命令白名单', function () {
    expect(fn() => $this->schedule->createTask(
        'dangerous-task',
        'dangerous:command',
        '* * * * *'
    ))->toThrow(\InvalidArgumentException::class, "Command 'dangerous:command' is not in the whitelist.");
});

it('可以更新任务', function () {
    $task = $this->schedule->createTask('update-test', 'test:command', '* * * * *');
    
    $updated = $this->schedule->updateTask($task, [
        'expression' => '0 2 * * *',
        'description' => '更新后的任务',
        'payload' => [
            'priority' => 'low',
            'dont_overlap' => true,
        ]
    ]);
    
    expect($updated->expression)->toBe('0 2 * * *');
    expect($updated->description)->toBe('更新后的任务');
    expect($updated->payload['priority'])->toBe('low');
    expect($updated->payload['dont_overlap'])->toBeTrue();
});

it('可以通过名称更新任务', function () {
    $this->schedule->createTask('named-task', 'test:command', '* * * * *');
    
    $updated = $this->schedule->updateTask('named-task', [
        'description' => '通过名称更新',
    ]);
    
    expect($updated->description)->toBe('通过名称更新');
});

it('可以删除任务', function () {
    $task = $this->schedule->createTask('delete-test', 'test:command', '* * * * *');
    
    $result = $this->schedule->deleteTask($task);
    expect($result)->toBeTrue();
    
    $found = $this->schedule->getTask('delete-test');
    expect($found)->toBeNull();
});

it('可以手动执行任务', function () {
    // Mock Artisan
    $artisan = \Mockery::mock(Kernel::class);
    $artisan->shouldReceive('call')
        ->with('test:command', [])
        ->andReturn(0);
    $artisan->shouldReceive('output')
        ->andReturn("Task executed successfully\nAll done!");
        
    // 重新创建 schedule 实例使用 mock 的 artisan
    $this->schedule = new LunaSchedule(
        app(LunaScheduleConfigure::class),
        $artisan,
        app('cache.store')
    );
    
    $task = $this->schedule->createTask('run-test', 'test:command', '* * * * *');
    
    $log = $this->schedule->runTask($task);
    
    expect($log)->toBeInstanceOf(ScheduleTaskLog::class);
    expect($log->task_id)->toBe($task->id);
    expect($log->isSuccess())->toBeTrue();
    expect($log->status)->toBe(1);
    expect($log->output)->toContain('Task executed successfully');
    expect($log->duration)->toBeGreaterThan(0);
    expect($log->ran_at)->not->toBeNull();
    expect($log->end_at)->not->toBeNull();
});

it('记录失败的任务执行', function () {
    // Mock Artisan 返回失败
    $artisan = \Mockery::mock(Kernel::class);
    $artisan->shouldReceive('call')
        ->with('test:fail', [])
        ->andReturn(1);
    $artisan->shouldReceive('output')
        ->andReturn("Error: Command failed");
        
    // 重新创建 schedule 实例使用 mock 的 artisan
    $this->schedule = new LunaSchedule(
        app(LunaScheduleConfigure::class),
        $artisan,
        app('cache.store')
    );
    
    $task = $this->schedule->createTask('fail-test', 'test:fail', '* * * * *');
    
    $log = $this->schedule->runTask($task);
    
    expect($log->isSuccess())->toBeFalse();
    expect($log->status)->toBe(0);
    expect($log->output)->toContain('Error: Command failed');
});

it('可以切换任务状态', function () {
    $task = $this->schedule->createTask('toggle-test', 'test:command', '* * * * *');
    
    // 禁用
    $disabled = $this->schedule->toggleTask($task, false);
    expect($disabled->enabled)->toBeFalse();
    
    // 启用
    $enabled = $this->schedule->toggleTask('toggle-test', true);
    expect($enabled->enabled)->toBeTrue();
});

it('可以获取任务列表', function () {
    $this->schedule->createTask('task1', 'test:1', '* * * * *');
    $this->schedule->createTask('task2', 'test:2', '* * * * *', ['enabled' => false]);
    $this->schedule->createTask('task3', 'test:3', '* * * * *');
    
    $all = $this->schedule->getTasks();
    expect($all)->toHaveCount(3);
    
    $active = $this->schedule->getTasks(true);
    expect($active)->toHaveCount(2);
});


it('可以执行命令并记录日志', function () {
    $artisan = \Mockery::mock(Kernel::class);
    $artisan->shouldReceive('call')
        ->with('cache:clear', ['--force' => true])
        ->andReturn(0);
    $artisan->shouldReceive('output')
        ->andReturn('Cache cleared!');
        
    // 重新创建 schedule 实例使用 mock 的 artisan
    $this->schedule = new LunaSchedule(
        app(LunaScheduleConfigure::class),
        $artisan,
        app('cache.store')
    );
    
    $log = $this->schedule->executeCommand(
        'cache:clear',
        ['--force' => true],
        null,
        '测试清理缓存'
    );
    
    expect($log)->toBeInstanceOf(CommandExecuteLog::class);
    expect($log->command)->toBe('cache:clear');
    expect($log->payload)->toBe(['--force' => true]);
    expect($log->comment)->toBe('测试清理缓存');
    expect($log->isSuccess())->toBeTrue();
    expect($log->output)->toBe('Cache cleared!');
    expect($log->operator_type)->toBe(hash_code('system'));
});

it('可以获取任务统计', function () {
    $task = $this->schedule->createTask('stats-test', 'test:command', '* * * * *');
    
    // 创建一些执行日志
    ScheduleTaskLog::create([
        'task_id' => $task->id,
        'ran_at' => now()->subDays(1),
        'end_at' => now()->subDays(1)->addSeconds(10),
        'duration' => 10,
        'status' => 1,
        'output' => 'Success 1',
    ]);
    
    ScheduleTaskLog::create([
        'task_id' => $task->id,
        'ran_at' => now()->subDays(2),
        'end_at' => now()->subDays(2)->addSeconds(20),
        'duration' => 20,
        'status' => 1,
        'output' => 'Success 2',
    ]);
    
    ScheduleTaskLog::create([
        'task_id' => $task->id,
        'ran_at' => now()->subDays(3),
        'end_at' => now()->subDays(3)->addSeconds(30),
        'duration' => 30,
        'status' => 0,
        'output' => 'Failed',
    ]);
    
    $stats = $this->schedule->getTaskStatistics($task, 7);
    
    expect($stats['total_runs'])->toBe(3);
    expect($stats['successful_runs'])->toBe(2);
    expect($stats['failed_runs'])->toBe(1);
    expect($stats['success_rate'])->toBe(66.67);
    expect($stats['average_duration'])->toBe(20.0);
    expect($stats['total_duration'])->toBe(60.0);
    expect($stats['last_run'])->not->toBeNull();
});

it('可以清理旧日志', function () {
    $task = $this->schedule->createTask('clean-test', 'test:command', '* * * * *');
    
    // 创建旧的任务日志
    ScheduleTaskLog::create([
        'task_id' => $task->id,
        'ran_at' => now()->subDays(40),
        'end_at' => now()->subDays(40),
        'duration' => 10,
        'status' => 1,
        'output' => 'Old',
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
    ]);
    
    // 创建新的任务日志
    ScheduleTaskLog::create([
        'task_id' => $task->id,
        'ran_at' => now()->subDays(10),
        'end_at' => now()->subDays(10),
        'duration' => 10,
        'status' => 1,
        'output' => 'Recent',
    ]);
    
    // 重新创建命令日志表记录，避免约束问题
    \DB::table('luna_command_execute_logs')->insert([
        'operator_type' => hash_code('system'),
        'operator_id' => 0,
        'command' => 'test:old',
        'payload' => json_encode([]),
        'comment' => 'Old command',
        'ran_at' => now()->subDays(40),
        'end_at' => now()->subDays(40),
        'duration' => 5,
        'status' => 1,
        'output' => 'Old output',
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
    ]);
    
    $deleted = $this->schedule->cleanOldLogs(30);
    
    expect($deleted)->toBe(2);
    
    // 验证旧日志已删除
    $remainingTaskLogs = ScheduleTaskLog::where('task_id', $task->id)->count();
    expect($remainingTaskLogs)->toBe(1);
    
    $remainingCommandLogs = CommandExecuteLog::count();
    expect($remainingCommandLogs)->toBe(0);
});

it('任务日志可以格式化持续时间', function () {
    $log = new ScheduleTaskLog();
    
    $log->duration = 0.5;
    expect($log->getFormattedDuration())->toBe('500ms');
    
    $log->duration = 45;
    expect($log->getFormattedDuration())->toBe('45s');
    
    $log->duration = 125;
    expect($log->getFormattedDuration())->toBe('2.08m');
});


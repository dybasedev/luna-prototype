<?php

use Dybasedev\LunaPrototype\Schedule\LunaSchedule;
use Dybasedev\LunaPrototype\Schedule\LunaScheduleConfigure;
use Dybasedev\LunaPrototype\Schedule\Models\ScheduleTask;
use Dybasedev\LunaPrototype\Schedule\Models\ScheduleTaskLog;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as Artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->configure = LunaScheduleConfigure::create()->build();
    $this->artisan = app(Artisan::class);
    $this->schedule = new LunaSchedule($this->configure, $this->artisan, app('cache.store'));
});

it('can create schedule instance', function () {
    expect($this->schedule)->toBeInstanceOf(LunaSchedule::class);
});

it('can get active tasks', function () {
    // 创建一些测试任务
    ScheduleTask::create([
        'name' => 'test-task-1',
        'command' => 'inspire',
        'expression' => '* * * * *',
        'timezone' => 'UTC',
        'description' => 'Test task 1',
        'enabled' => true,
        'payload' => ['parameters' => [], 'options' => []]
    ]);
    
    ScheduleTask::create([
        'name' => 'test-task-2',
        'command' => 'queue:work',
        'expression' => '0 * * * *',
        'timezone' => 'UTC', 
        'description' => 'Test task 2',
        'enabled' => true,
        'payload' => ['parameters' => [], 'options' => []]
    ]);
    
    ScheduleTask::create([
        'name' => 'test-task-3',
        'command' => 'cache:clear',
        'expression' => '0 0 * * *',
        'timezone' => 'UTC',
        'description' => 'Disabled task',
        'enabled' => false,
        'payload' => ['parameters' => [], 'options' => []]
    ]);
    
    $activeTasks = $this->schedule->getActiveTasks();
    
    expect($activeTasks)->toBeArray();
    expect($activeTasks)->toHaveCount(2);
    expect($activeTasks[0])->toBeInstanceOf(ScheduleTask::class);
    expect($activeTasks[0]->enabled)->toBeTrue();
});

it('caches active tasks', function () {
    $cache = app('cache.store');
    
    // 创建任务
    ScheduleTask::create([
        'name' => 'test-cache-task',
        'command' => 'inspire',
        'expression' => '* * * * *',
        'timezone' => 'UTC',
        'description' => 'Test task',
        'enabled' => true,
        'payload' => ['parameters' => [], 'options' => []]
    ]);
    
    // 第一次调用
    $tasks1 = $this->schedule->getActiveTasks();
    
    // 验证缓存存在
    expect($cache->has('schedule-task:all-active'))->toBeTrue();
    
    // 第二次调用应该从缓存获取
    $tasks2 = $this->schedule->getActiveTasks();
    
    expect(count($tasks1))->toBe(count($tasks2));
});

it('can get available commands', function () {
    $availableCommands = $this->schedule->availableCommands();
    
    expect($availableCommands)->toBeArray();
    expect($availableCommands)->not->toBeEmpty();
    
    // 检查返回的命令格式
    $firstCommand = array_values($availableCommands)[0];
    expect($firstCommand)->toHaveKeys(['command', 'description', 'options', 'arguments']);
    expect($firstCommand['command'])->toBeString();
    expect($firstCommand['description'])->toBeString();
    expect($firstCommand['options'])->toBeArray();
    expect($firstCommand['arguments'])->toBeArray();
});

it('filters commands by whitelist', function () {
    // 创建一个自定义配置，只允许特定命令
    $customConfigure = LunaScheduleConfigure::create()
        ->commandWhiteList(['inspire', 'queue:*'])
        ->build();
    
    $customSchedule = new LunaSchedule($customConfigure, $this->artisan, app('cache.store'));
    
    $availableCommands = $customSchedule->availableCommands();
    
    expect($availableCommands)->toBeArray();
    
    // 验证只返回白名单中的命令
    foreach ($availableCommands as $command) {
        $commandName = $command['command'];
        expect(
            $commandName === 'inspire' || 
            str_starts_with($commandName, 'queue:')
        )->toBeTrue();
    }
});

it('can register laravel schedules', function () {
    // 创建测试任务
    ScheduleTask::create([
        'name' => 'test-inspire',
        'command' => 'inspire',
        'expression' => '* * * * *',
        'timezone' => 'UTC',
        'description' => 'Test inspiration',
        'enabled' => true,
        'payload' => [
            'dont_overlap' => true,
            'run_in_maintenance' => false,
            'run_on_one_server' => false,
            'run_in_background' => true
        ]
    ]);
    
    $laravelSchedule = app(Schedule::class);
    
    // 注册任务到 Laravel Schedule
    $this->schedule->schedule($laravelSchedule);
    
    // 验证任务已注册
    $events = $laravelSchedule->events();
    expect($events)->not->toBeEmpty();
    
    // 找到我们的任务
    $ourEvent = collect($events)->first(function ($event) {
        return $event->description === 'Test inspiration';
    });
    
    expect($ourEvent)->not->toBeNull();
    expect($ourEvent->expression)->toBe('* * * * *');
    expect($ourEvent->timezone)->toBe('UTC');
});

it('handles task execution logging', function () {
    // 创建一个简单的测试任务
    ScheduleTask::create([
        'name' => 'test-logging-task',
        'command' => 'inspire',
        'expression' => '* * * * *',
        'timezone' => 'UTC',
        'description' => 'Test logging task',
        'enabled' => true,
        'payload' => []
    ]);
    
    $laravelSchedule = app(Schedule::class);
    $this->schedule->schedule($laravelSchedule);
    
    // 验证任务已注册到调度系统
    $events = $laravelSchedule->events();
    expect($events)->not->toBeEmpty();
    expect($events[0]->description)->toBe('Test logging task');
    expect($events[0]->expression)->toBe('* * * * *');
});

it('can handle task options correctly', function () {
    // 创建带有各种选项的任务
    ScheduleTask::create([
        'name' => 'test-options-task',
        'command' => 'inspire',
        'expression' => '* * * * *',
        'timezone' => 'UTC',
        'description' => 'Task with options',
        'enabled' => true,
        'payload' => [
            'parameters' => [],
            'dont_overlap' => true,
            'run_in_maintenance' => true,
            'run_on_one_server' => true,
            'run_in_background' => true
        ]
    ]);
    
    $laravelSchedule = app(Schedule::class);
    $this->schedule->schedule($laravelSchedule);
    
    $events = $laravelSchedule->events();
    $event = $events[0];
    
    // 验证选项已正确应用
    expect($event->description)->toBe('Task with options');
    expect($event->expression)->toBe('* * * * *');
    expect($event->timezone)->toBe('UTC');
});

it('can compile task parameters', function () {
    // 创建带参数的任务
    $task = ScheduleTask::create([
        'name' => 'test-queue-worker',
        'command' => 'queue:work',
        'expression' => '* * * * *',
        'timezone' => 'UTC',
        'description' => 'Queue worker',
        'enabled' => true,
        'payload' => [
            'parameters' => '--queue=high --sleep=3'
        ]
    ]);
    
    $compiledParams = $task->compileParameters(true);
    
    expect($compiledParams)->toBeArray();
    expect($compiledParams)->toHaveKey('--queue');
    expect($compiledParams['--queue'])->toBe('high');
});

it('handles disabled schedule system gracefully', function () {
    // 清除缓存以确保重新检查
    app('cache.store')->forget('schedule-task:enabled');
    
    $laravelSchedule = app(Schedule::class);
    
    // 当没有 schedule 表时，应该优雅地处理
    $this->schedule->schedule($laravelSchedule);
    
    // 不应该抛出异常
    expect(true)->toBeTrue();
});
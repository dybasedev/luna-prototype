<?php

use Dybasedev\LunaPrototype\Content\Models\Content;
use Dybasedev\LunaPrototype\Content\LunaContent;
use Dybasedev\LunaPrototype\Content\LunaContentConfigure;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// 测试用的 SessionHolder
class TestOptimizationOwner implements SessionHolder
{
    public function getOperatorId(): int
    {
        return 1;
    }

    public function getOperatorType(): int
    {
        return 1;
    }

    public function getOperatorTypeName(): string
    {
        return 'test_owner';
    }

    public function getSessionHolderContext(): ?array
    {
        return ['role' => 'admin'];
    }
}

test('启用版本控制时payload总是从版本表读取', function () {
    $configure = LunaContentConfigure::create()->build();
    expect($configure->enableVersioning)->toBeTrue();
    
    $lunaContent = new LunaContent(
        $configure,
        app(\Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler::class),
        Cache::store('array')
    );
    
    $owner = new TestOptimizationOwner();
    
    // 创建内容
    $content = $lunaContent->createContent([
        'name' => 'versioned-content',
        'title' => '版本化内容',
        'content' => '内容正文',
        'payload' => ['source' => 'version', 'data' => 'v1'],
    ], $owner);
    
    // 验证内容表中没有 payload 字段
    $columns = \Schema::getColumnListing('luna_contents');
    expect($columns)->not->toContain('payload');
    
    // 但通过模型访问时，应该从版本表读取
    expect($content->payload)->toMatchArray(['source' => 'version', 'data' => 'v1']);
});

test('禁用版本控制时不能使用content和payload', function () {
    // 创建禁用版本控制的配置
    $configure = new class extends LunaContentConfigure {
        protected(set) bool $enableVersioning = false;
    };
    
    $configureInstance = $configure::create()->build();
    expect($configureInstance->enableVersioning)->toBeFalse();
    
    $lunaContent = new LunaContent(
        $configureInstance,
        app(\Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler::class),
        Cache::store('array')
    );
    
    $owner = new TestOptimizationOwner();
    
    // 尝试创建带 content 的内容应该抛出异常
    expect(fn() => $lunaContent->createContent([
        'name' => 'non-versioned-content',
        'title' => '非版本化内容',
        'content' => '内容',
    ], $owner))->toThrow(\LogicException::class, 'Content and payload require versioning to be enabled');
    
    // 尝试创建带 payload 的内容也应该抛出异常
    expect(fn() => $lunaContent->createContent([
        'name' => 'non-versioned-content-2',
        'title' => '非版本化内容2',
        'payload' => ['data' => 'test'],
    ], $owner))->toThrow(\LogicException::class, 'Content and payload require versioning to be enabled');
});

test('版本化内容切换版本时payload正确切换', function () {
    $content = Content::create([
        'name' => 'version-switch-payload',
        'title' => '版本切换测试',
    ]);
    
    // 创建多个版本
    $v1 = $content->createVersion('版本1内容', [
        'payload' => ['version' => 1, 'feature' => 'basic'],
    ]);
    
    $v2 = $content->createVersion('版本2内容', [
        'payload' => ['version' => 2, 'feature' => 'advanced'],
    ]);
    
    $content->applyVersion($v2->version_id);
    $content->refresh();
    
    // 应该从当前版本（v2）获取 payload
    expect($content->payload)->toMatchArray(['version' => 2, 'feature' => 'advanced']);
    
    // 切换到 v1
    $content->applyVersion($v1->version_id);
    $content->refresh();
    
    // 应该从 v1 获取 payload
    expect($content->payload)->toMatchArray(['version' => 1, 'feature' => 'basic']);
});
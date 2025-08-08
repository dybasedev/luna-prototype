<?php

use Dybasedev\LunaPrototype\Content\Models\Content;
use Dybasedev\LunaPrototype\Content\Models\ContentVersion;
use Dybasedev\LunaPrototype\Content\LunaContent;
use Dybasedev\LunaPrototype\Content\LunaContentConfigure;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// 测试用的 SessionHolder
class TestPayloadOwner implements SessionHolder
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

beforeEach(function () {
    $this->configure = LunaContentConfigure::create()->build();
    // 版本控制默认是启用的，所以不需要特别设置
    
    $this->lunaContent = new LunaContent(
        $this->configure,
        app(\Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler::class),
        Cache::store('array')
    );
    
    $this->owner = new TestPayloadOwner();
});

test('创建内容时版本包含payload', function () {
    $data = [
        'name' => 'test-content-with-payload',
        'title' => '测试内容',
        'content' => '内容正文',
        'payload' => [
            'author' => '张三',
            'tags' => ['测试', 'payload'],
            'meta' => ['description' => '测试描述'],
        ],
    ];

    $content = $this->lunaContent->createContent($data, $this->owner);
    
    expect($content->payload)->toMatchArray($data['payload']);
    expect($content->versions)->toHaveCount(1);
    
    $version = $content->currentVersion;
    expect($version)->not->toBeNull();
    expect($version->content)->toBe('内容正文');
    expect($version->payload)->toMatchArray($data['payload']);
});

test('更新内容时新版本包含更新的payload', function () {
    // 创建初始内容
    $content = $this->lunaContent->createContent([
        'name' => 'update-payload-test',
        'title' => '初始标题',
        'content' => '初始内容',
        'payload' => ['version' => 1, 'author' => '张三'],
    ], $this->owner);

    // 更新内容和payload
    $updated = $this->lunaContent->updateContent($content, [
        'title' => '更新的标题',
        'content' => '更新的内容',
        'payload' => ['version' => 2, 'author' => '李四', 'updated' => true],
    ], $this->owner);

    expect($updated->title)->toBe('更新的标题');
    expect($updated->payload)->toMatchArray(['version' => 2, 'author' => '李四', 'updated' => true]);
    expect($updated->versions)->toHaveCount(2);
    
    // 检查新版本
    $newVersion = $updated->currentVersion;
    expect($newVersion->content)->toBe('更新的内容');
    expect($newVersion->payload)->toMatchArray(['version' => 2, 'author' => '李四', 'updated' => true]);
});

test('只更新payload不更新content时需要创建新版本', function () {
    $content = $this->lunaContent->createContent([
        'name' => 'payload-only-update',
        'title' => '测试标题',
        'content' => '内容不变',
        'payload' => ['status' => 'draft'],
    ], $this->owner);

    // 更新 payload 也需要创建新版本（提供相同的 content）
    $updated = $this->lunaContent->updateContent($content, [
        'content' => '内容不变',  // 必须提供 content 来创建新版本
        'payload' => ['status' => 'published', 'publishedAt' => '2023-12-01'],
        'version_name' => '更新状态',
    ], $this->owner);

    // payload应该被更新，创建了新版本
    expect($updated->payload)->toMatchArray(['status' => 'published', 'publishedAt' => '2023-12-01']);
    expect($updated->versions)->toHaveCount(2); // 有两个版本
});

test('切换版本时恢复对应的payload', function () {
    $content = Content::create([
        'name' => 'version-switch-test',
        'title' => '版本切换测试',
        'payload' => ['version' => 1],
    ]);

    // 创建第一个版本
    $version1 = $content->createVersion('内容版本1', [
        'version_name' => '版本1',
        'payload' => ['version' => 1, 'data' => 'v1-data'],
    ]);

    // 创建第二个版本
    $version2 = $content->createVersion('内容版本2', [
        'version_name' => '版本2',
        'payload' => ['version' => 2, 'data' => 'v2-data'],
    ]);
    
    // 应用版本2
    $content->applyVersion($version2->version_id);

    // 当前应该是版本2
    expect($content->fresh()->payload)->toMatchArray(['version' => 2, 'data' => 'v2-data']);
    expect($content->fresh()->content)->toBe('内容版本2');

    // 切换回版本1
    $content->applyVersion($version1->version_id);
    
    $content->refresh();
    expect($content->payload)->toMatchArray(['version' => 1, 'data' => 'v1-data']);
    expect($content->content)->toBe('内容版本1');

    // 切换回版本2
    $content->applyVersion($version2->version_id);
    
    $content->refresh();
    expect($content->payload)->toMatchArray(['version' => 2, 'data' => 'v2-data']);
    expect($content->content)->toBe('内容版本2');
});

test('版本创建时没有指定payload则使用空数组', function () {
    $content = Content::create([
        'name' => 'inherit-payload-test',
        'title' => '继承payload测试',
    ]);
    
    // 先创建一个带 payload 的版本
    $v1 = $content->createVersion('初始内容', [
        'payload' => ['original' => true, 'data' => 'test'],
    ]);

    // 创建第二个版本时不指定payload
    $v2 = $content->createVersion('新内容', [
        'version_name' => '新版本',
    ]);

    // 第二个版本应该继承当前内容的 payload（来自 v1）
    expect($v2->payload)->toMatchArray(['original' => true, 'data' => 'test']);
});
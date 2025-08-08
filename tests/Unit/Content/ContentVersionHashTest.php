<?php

use Dybasedev\LunaPrototype\Content\Models\Content;
use Dybasedev\LunaPrototype\Content\Models\ContentVersion;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// 测试用的 SessionHolder
class TestHashUser implements SessionHolder
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
        return 'test_user';
    }

    public function getSessionHolderContext(): ?array
    {
        return ['role' => 'admin'];
    }
}

test('相同内容的版本使用相同的哈希值', function () {
    $content = Content::create([
        'name' => 'hash-test-content',
        'title' => '哈希测试内容',
    ]);
    
    $user = new TestHashUser();
    
    // 创建第一个版本
    $version1 = $content->createVersion('测试内容', [
        'payload' => ['key' => 'value'],
        'version_name' => '版本1',
    ], $user);
    
    // 创建相同内容的第二个版本（不同的版本名称不影响哈希）
    $version2 = $content->createVersion('测试内容', [
        'payload' => ['key' => 'value'],
        'version_name' => '版本2',
    ], $user);
    
    // 由于内容相同，应该重用相同的版本ID（哈希值）
    expect($version2->version_id)->toBe($version1->version_id);
    
    // 版本数量应该只有1个（去重了）
    expect($content->versions()->count())->toBe(1);
});

test('不同内容的版本使用不同的哈希值', function () {
    $content = Content::create([
        'name' => 'hash-diff-test',
        'title' => '哈希差异测试',
    ]);
    
    // 创建第一个版本
    $version1 = $content->createVersion('内容1', [
        'payload' => ['key' => 'value1'],
    ]);
    
    // 创建不同内容的第二个版本
    $version2 = $content->createVersion('内容2', [
        'payload' => ['key' => 'value2'],
    ]);
    
    // 内容不同，应该有不同的版本ID
    expect($version2->version_id)->not->toBe($version1->version_id);
    
    // 版本数量应该是2个
    expect($content->versions()->count())->toBe(2);
});

test('payload顺序不影响哈希值', function () {
    $content = Content::create([
        'name' => 'hash-order-test',
        'title' => '哈希顺序测试',
    ]);
    
    // 创建第一个版本
    $version1 = $content->createVersion('测试内容', [
        'payload' => [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ],
    ]);
    
    // 创建相同内容但payload顺序不同的版本
    $version2 = $content->createVersion('测试内容', [
        'payload' => [
            'key3' => 'value3',
            'key1' => 'value1',
            'key2' => 'value2',
        ],
    ]);
    
    // 顺序不同但内容相同，应该使用相同的哈希
    expect($version2->version_id)->toBe($version1->version_id);
});
<?php

use Dybasedev\LunaPrototype\Content\Models\Content;
use Dybasedev\LunaPrototype\Content\Models\ContentChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // 运行迁移
    $migration = include __DIR__ . '/../../../src/Content/migrations/0001_01_01_000000_create_luna_prototype_content_tables.php';
    $migration->up();
});

test('可以创建频道', function () {
    $channel = ContentChannel::create([
        'id' => hash_code('news'),
        'name' => 'news',
        'display_name' => '新闻频道',
        'description' => '发布新闻资讯',
        'handler_id' => 1,
        'config' => [
            'auto_publish' => true,
            'max_contents' => 100,
        ],
        'sort' => 10,
    ]);

    expect($channel)->toBeInstanceOf(ContentChannel::class);
    expect($channel->id)->toBe(hash_code('news'));
    expect($channel->name)->toBe('news');
    expect($channel->display_name)->toBe('新闻频道');
    expect($channel->config)->toBe([
        'auto_publish' => true,
        'max_contents' => 100,
    ]);
    expect($channel->is_active)->toBeTrue();
});

test('可以按名称查找频道', function () {
    ContentChannel::create([
        'id' => hash_code('tech'),
        'name' => 'tech',
        'display_name' => '科技频道',
        'handler_id' => 1,
        'config' => [],
    ]);

    $found = ContentChannel::findByName('tech');
    expect($found)->toBeInstanceOf(ContentChannel::class);
    expect($found->display_name)->toBe('科技频道');

    $notFound = ContentChannel::findByName('non-existent');
    expect($notFound)->toBeNull();
});

test('可以管理频道的内容', function () {
    $channel = ContentChannel::create([
        'id' => hash_code('blog'),
        'name' => 'blog',
        'display_name' => '博客',
        'handler_id' => 1,
        'config' => [],
    ]);

    // 创建几个内容
    $content1 = Content::create([
        'name' => 'blog-post-1',
        'title' => '博客文章1',
        'payload' => [],
    ]);

    $content2 = Content::create([
        'name' => 'blog-post-2',
        'title' => '博客文章2',
        'payload' => [],
        'published_at' => now(),
    ]);

    $content3 = Content::create([
        'name' => 'blog-post-3',
        'title' => '博客文章3',
        'payload' => [],
        'published_at' => now(),
    ]);

    // 将内容添加到频道
    $channel->contents()->attach($content1->id, ['sort' => 1]);
    $channel->contents()->attach($content2->id, ['sort' => 2]);
    $channel->contents()->attach($content3->id, ['sort' => 3]);

    // 测试关联
    expect($channel->contents)->toHaveCount(3);
    expect($channel->contents->pluck('name')->toArray())->toBe(['blog-post-1', 'blog-post-2', 'blog-post-3']);

    // 测试已发布内容
    expect($channel->publishedContents()->get())->toHaveCount(2);
    expect($channel->publishedContents()->pluck('name')->toArray())->toContain('blog-post-2', 'blog-post-3');
});

test('活跃频道查询作用域', function () {
    // 创建活跃频道
    ContentChannel::create([
        'id' => hash_code('active-1'),
        'name' => 'active-1',
        'display_name' => '活跃频道1',
        'handler_id' => 1,
        'config' => [],
        'is_active' => true,
    ]);

    ContentChannel::create([
        'id' => hash_code('active-2'),
        'name' => 'active-2',
        'display_name' => '活跃频道2',
        'handler_id' => 1,
        'config' => [],
        'is_active' => true,
    ]);

    // 创建非活跃频道
    ContentChannel::create([
        'id' => hash_code('inactive'),
        'name' => 'inactive',
        'display_name' => '非活跃频道',
        'handler_id' => 1,
        'config' => [],
        'is_active' => false,
    ]);

    // 测试活跃查询
    $activeChannels = ContentChannel::active()->get();
    expect($activeChannels)->toHaveCount(2);
    expect($activeChannels->pluck('name')->toArray())->toContain('active-1', 'active-2');
    expect($activeChannels->pluck('name')->toArray())->not->toContain('inactive');
});

test('频道排序', function () {
    ContentChannel::create([
        'id' => hash_code('channel-3'),
        'name' => 'channel-3',
        'display_name' => '频道3',
        'handler_id' => 1,
        'config' => [],
        'sort' => 30,
    ]);

    ContentChannel::create([
        'id' => hash_code('channel-1'),
        'name' => 'channel-1',
        'display_name' => '频道1',
        'handler_id' => 1,
        'config' => [],
        'sort' => 10,
    ]);

    ContentChannel::create([
        'id' => hash_code('channel-2'),
        'name' => 'channel-2',
        'display_name' => '频道2',
        'handler_id' => 1,
        'config' => [],
        'sort' => 20,
    ]);

    $channels = ContentChannel::ordered()->get();
    expect($channels->pluck('name')->toArray())->toBe(['channel-1', 'channel-2', 'channel-3']);
});

test('频道配置的JSON转换', function () {
    $config = [
        'auto_publish' => true,
        'require_review' => false,
        'max_contents' => 50,
        'custom_settings' => [
            'theme' => 'dark',
            'layout' => 'grid',
        ],
    ];

    $channel = ContentChannel::create([
        'id' => hash_code('json-channel'),
        'name' => 'json-channel',
        'display_name' => 'JSON频道',
        'handler_id' => 1,
        'config' => $config,
    ]);

    // 从数据库重新加载
    $loaded = ContentChannel::find($channel->id);
    expect($loaded->config)->toEqual($config);
    expect($loaded->config['custom_settings']['theme'])->toBe('dark');
});

test('频道与内容的中间表数据', function () {
    $channel = ContentChannel::create([
        'id' => hash_code('pivot-channel'),
        'name' => 'pivot-channel',
        'display_name' => '中间表测试频道',
        'handler_id' => 1,
        'config' => [],
    ]);

    $content = Content::create([
        'name' => 'pivot-content',
        'title' => '中间表测试内容',
        'payload' => [],
    ]);

    // 附加内容到频道，带有额外数据
    $pivotData = [
        'sort' => 100,
        'config' => json_encode(['featured' => true, 'pin_top' => false]),
    ];
    
    $channel->contents()->attach($content->id, $pivotData);

    // 验证中间表数据
    $attached = $channel->contents()->first();
    expect($attached->pivot->sort)->toBe(100);
    expect(json_decode($attached->pivot->config, true))->toMatchArray(['featured' => true, 'pin_top' => false]);
    expect($attached->pivot->created_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});
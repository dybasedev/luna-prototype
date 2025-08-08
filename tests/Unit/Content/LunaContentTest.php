<?php

use Dybasedev\LunaPrototype\Content\LunaContent;
use Dybasedev\LunaPrototype\Content\LunaContentConfigure;
use Dybasedev\LunaPrototype\Content\Models\Content;
use Dybasedev\LunaPrototype\Content\Models\ContentChannel;
use Dybasedev\LunaPrototype\Content\Models\ContentCategory;
use Dybasedev\LunaPrototype\Content\Models\ContentVersion;
use Dybasedev\LunaPrototype\Content\Models\ContentMetadata;
use Dybasedev\LunaPrototype\Content\Handlers\DefaultChannelHandler;
use Dybasedev\LunaPrototype\Content\Handlers\DefaultContentHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// 测试用的 SessionHolder
class TestContentOwner implements SessionHolder
{
    public function __construct(
        private int $id = 1,
        private int $type = 1
    ) {}

    public function getOperatorId(): int
    {
        return $this->id;
    }

    public function getOperatorType(): int
    {
        return $this->type;
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
    // Handler表应该由Foundation迁移创建，这里不需要手动创建

    // 设置测试环境
    $this->configure = LunaContentConfigure::create()->build();
    $this->lunaHandler = app(LunaHandler::class);
    
    // 注册 Content 组件的处理器
    $this->configure->boot(app());
    
    $this->lunaContent = new LunaContent(
        $this->configure,
        $this->lunaHandler,
        Cache::store('array')
    );
    
    $this->owner = new TestContentOwner();

    // 清除缓存以确保新的处理器记录被加载
    Cache::flush();

    // 创建测试处理器记录 - 使用 create 而不是 forceCreate，让 NamedId trait 自动处理 id
    $this->defaultChannelHandler = Handler::create([
        'name' => 'default-channel-handler',
        'group_id' => hash_code('channel-handlers'),
        'display_name' => '默认频道处理器',
        'handler' => DefaultChannelHandler::class,
        'config' => [],
        'enabled' => true,
    ]);
    
    $this->defaultContentHandler = Handler::create([
        'name' => 'default-content-handler',
        'group_id' => hash_code('content-handlers'),
        'display_name' => '默认内容处理器',
        'handler' => DefaultContentHandler::class,
        'config' => [],
        'enabled' => true,
    ]);
});

test('可以创建内容', function () {
    $data = [
        'name' => 'test-article',
        'title' => '测试文章',
        'keywords' => '测试,文章',
        'description' => '这是一篇测试文章',
        'content' => '文章内容',
        'payload' => ['custom' => 'data'],
    ];

    $content = $this->lunaContent->createContent($data, $this->owner);

    expect($content)->toBeInstanceOf(Content::class);
    expect($content->name)->toBe('test-article');
    expect($content->title)->toBe('测试文章');
    expect($content->owner_type)->toBe(hash_code(get_class($this->owner)));
    expect($content->owner_id)->toBe($this->owner->getOperatorId());
    
    // 启用版本控制时，应该创建初始版本
    if ($this->configure->enableVersioning) {
        expect($content->versions)->toHaveCount(1);
        expect($content->currentVersion->content)->toBe('文章内容');
    }
});

test('可以更新内容', function () {
    $content = $this->lunaContent->createContent([
        'name' => 'update-test',
        'title' => '原标题',
        'content' => '原内容',
        'payload' => [],
    ], $this->owner);

    $updated = $this->lunaContent->updateContent($content, [
        'title' => '新标题',
        'description' => '新描述',
        'content' => '新内容',
        'version_name' => '更新版本',
    ], $this->owner);

    expect($updated->title)->toBe('新标题');
    expect($updated->description)->toBe('新描述');
    
    if ($this->configure->enableVersioning) {
        expect($updated->versions)->toHaveCount(2);
        expect($updated->currentVersion->content)->toBe('新内容');
        expect($updated->currentVersion->version_name)->toBe('更新版本');
    }
});

test('可以删除内容', function () {
    $content = $this->lunaContent->createContent([
        'name' => 'delete-test',
        'title' => '待删除内容',
        'payload' => [],
    ]);

    // 添加一些关联数据
    $content->setMetadata('key', 'value');
    
    $result = $this->lunaContent->deleteContent($content);
    
    expect($result)->toBeTrue();
    expect(Content::find($content->id))->toBeNull();
    expect(ContentMetadata::where('content_id', $content->id)->count())->toBe(0);
});

test('可以创建或更新频道', function () {
    $channel = $this->lunaContent->createOrUpdateChannel('news', [
        'display_name' => '新闻频道',
        'description' => '最新新闻',
        'handler_id' => $this->defaultChannelHandler->id,
        'config' => ['auto_publish' => true],
    ]);

    expect($channel)->toBeInstanceOf(ContentChannel::class);
    expect($channel->id)->toBe(hash_code('news'));
    expect($channel->display_name)->toBe('新闻频道');

    // 更新频道
    $updated = $this->lunaContent->createOrUpdateChannel('news', [
        'display_name' => '更新的新闻频道',
    ]);

    expect($updated->id)->toBe($channel->id);
    expect($updated->display_name)->toBe('更新的新闻频道');
});

test('可以创建分类', function () {
    $category = $this->lunaContent->createCategory([
        'name' => 'tech',
        'display_name' => '科技',
        'description' => '科技相关内容',
    ]);

    expect($category)->toBeInstanceOf(ContentCategory::class);
    expect($category->name)->toBe('tech');
    expect($category->display_name)->toBe('科技');
});

test('可以获取分类树', function () {
    // 创建分类结构
    $root1 = $this->lunaContent->createCategory([
        'name' => 'root1',
        'display_name' => '根分类1',
    ]);

    $child1 = $this->lunaContent->createCategory([
        'parent_id' => $root1->id,
        'name' => 'child1',
        'display_name' => '子分类1',
    ]);

    $root2 = $this->lunaContent->createCategory([
        'name' => 'root2',
        'display_name' => '根分类2',
    ]);

    $child2 = $this->lunaContent->createCategory([
        'parent_id' => $root2->id,
        'name' => 'child2',
        'display_name' => '子分类2',
    ]);

    // 获取分类树
    $tree = $this->lunaContent->getCategoryTree();

    expect($tree)->toHaveCount(2);
    expect($tree->pluck('name')->toArray())->toBe(['root1', 'root2']);
    expect($tree->first()->children)->toHaveCount(1);
    expect($tree->first()->children->first()->name)->toBe('child1');
});

test('可以发布内容到频道', function () {
    $content = $this->lunaContent->createContent([
        'name' => 'channel-content',
        'title' => '频道内容',
        'payload' => [],
    ]);

    $channel = $this->lunaContent->createOrUpdateChannel('blog', [
        'display_name' => '博客',
        'handler_id' => $this->defaultChannelHandler->id,
        'config' => [],
    ]);

    $result = $this->lunaContent->publishToChannel(
        $content,
        $channel,
        ['sort' => 10],
        $this->owner
    );

    expect($result)->toBeTrue();
    
    $content->refresh();
    expect($content->channels)->toHaveCount(1);
    expect($content->channels->first()->id)->toBe($channel->id);
});

test('可以从频道移除内容', function () {
    $content = $this->lunaContent->createContent([
        'name' => 'removable-content',
        'title' => '可移除内容',
        'payload' => [],
    ]);

    $channel = $this->lunaContent->createOrUpdateChannel('temp', [
        'display_name' => '临时频道',
        'handler_id' => $this->defaultChannelHandler->id,
        'config' => [],
    ]);

    // 先发布到频道
    $this->lunaContent->publishToChannel($content, $channel);
    
    // 然后移除
    $result = $this->lunaContent->removeFromChannel($content, $channel);

    expect($result)->toBeTrue();
    
    $content->refresh();
    expect($content->channels)->toHaveCount(0);
});

test('可以搜索内容', function () {
    // 创建一些测试内容
    $this->lunaContent->createContent([
        'name' => 'search-1',
        'title' => 'Laravel 教程',
        'description' => '学习 Laravel 框架',
        'keywords' => 'laravel,php,框架',
        'payload' => [],
        'published_at' => now(),
    ]);

    $this->lunaContent->createContent([
        'name' => 'search-2',
        'title' => 'Vue.js 入门',
        'description' => 'Vue.js 基础教程',
        'keywords' => 'vue,javascript,前端',
        'payload' => [],
        'published_at' => now(),
    ]);

    $this->lunaContent->createContent([
        'name' => 'search-3',
        'title' => 'PHP 最佳实践',
        'description' => 'PHP 编程最佳实践',
        'keywords' => 'php,编程,最佳实践',
        'payload' => [],
    ]);

    // 搜索包含 "PHP" 的内容
    $results = $this->lunaContent->searchContents('PHP')->get();
    expect($results)->toHaveCount(2);
    expect($results->pluck('name')->toArray())->toContain('search-1', 'search-3');

    // 搜索已发布的内容
    $published = $this->lunaContent->searchContents('教程', ['published' => true])->get();
    expect($published)->toHaveCount(2);
    expect($published->pluck('name')->toArray())->toContain('search-1', 'search-2');
});

test('可以获取内容统计信息', function () {
    // 创建测试数据
    $channel = $this->lunaContent->createOrUpdateChannel('stats-channel', [
        'display_name' => '统计频道',
        'handler_id' => $this->defaultChannelHandler->id,
        'config' => [],
    ]);

    $category = $this->lunaContent->createCategory([
        'name' => 'stats-category',
        'display_name' => '统计分类',
    ]);

    // 创建已发布内容
    $published1 = $this->lunaContent->createContent([
        'name' => 'stats-published-1',
        'title' => '已发布1',
        'payload' => [],
        'published_at' => now()->subDays(1),
        'views_count' => 100,
    ]);
    $published1->channels()->attach($channel->id);
    $published1->categories()->attach($category->id);

    $published2 = $this->lunaContent->createContent([
        'name' => 'stats-published-2',
        'title' => '已发布2',
        'payload' => [],
        'published_at' => now()->subHours(1),
        'views_count' => 50,
    ]);

    // 创建未发布内容
    $unpublished = $this->lunaContent->createContent([
        'name' => 'stats-unpublished',
        'title' => '未发布',
        'payload' => [],
    ]);

    $stats = $this->lunaContent->getContentStatistics();

    expect($stats['total'])->toBe(3);
    expect($stats['published'])->toBe(2);
    expect($stats['unpublished'])->toBe(1);
    expect($stats['total_views'])->toBe('150');
});

test('可以验证内容数据', function () {
    $data = [
        'name' => 'valid-content',
        'title' => '有效内容',
    ];

    $validator = $this->lunaContent->validateContent($data);
    expect($validator->passes())->toBeTrue();

    // 测试必填字段
    $invalidData = [
        'description' => '只有描述',
    ];

    $invalidValidator = $this->lunaContent->validateContent($invalidData);
    expect($invalidValidator->fails())->toBeTrue();
    expect($invalidValidator->errors()->has('name'))->toBeTrue();
    expect($invalidValidator->errors()->has('title'))->toBeTrue();

    // 测试唯一性
    $this->lunaContent->createContent([
        'name' => 'unique-name',
        'title' => '唯一名称',
        'payload' => [],
    ]);

    $duplicateData = [
        'name' => 'unique-name',
        'title' => '重复名称',
    ];

    $duplicateValidator = $this->lunaContent->validateContent($duplicateData);
    expect($duplicateValidator->fails())->toBeTrue();
    expect($duplicateValidator->errors()->has('name'))->toBeTrue();
});

test('可以渲染内容', function () {
    // 使用handler_id 2 对应HtmlContentHandler
    
    $content = $this->lunaContent->createContent([
        'name' => 'renderable',
        'title' => '可渲染内容',
        'handler_id' => $this->defaultContentHandler->id,
        'payload' => [],
        'content' => '<p>HTML内容</p>',
    ], $this->owner);

    $rendered = $this->lunaContent->renderContent($content);
    
    expect($rendered)->toBeInstanceOf(\Dybasedev\LunaPrototype\Content\Results\ContentResult::class);
    
    $renderedArray = $rendered->toArray();
    expect($renderedArray)->toBeArray();
    expect($renderedArray)->toHaveKey('id');
    expect($renderedArray)->toHaveKey('title');
    expect($renderedArray)->toHaveKey('content');
    expect($renderedArray['content'])->toBe('<p>HTML内容</p>');
});

test('启用分类时可以管理内容分类', function () {
    if (!$this->configure->enableCategories) {
        $this->configure->enableCategories = true;
    }

    $category1 = $this->lunaContent->createCategory([
        'name' => 'cat1',
        'display_name' => '分类1',
    ]);

    $category2 = $this->lunaContent->createCategory([
        'name' => 'cat2',
        'display_name' => '分类2',
    ]);

    $content = $this->lunaContent->createContent([
        'name' => 'categorized',
        'title' => '分类内容',
        'payload' => [],
        'categories' => [$category1->id, $category2->id],
    ], $this->owner);

    expect($content->categories)->toHaveCount(2);
    expect($content->categories->pluck('name')->toArray())->toContain('cat1', 'cat2');

    // 更新分类
    $updated = $this->lunaContent->updateContent($content, [
        'categories' => [$category1->id], // 只保留一个分类
    ]);

    expect($updated->categories)->toHaveCount(1);
    expect($updated->categories->first()->name)->toBe('cat1');
});
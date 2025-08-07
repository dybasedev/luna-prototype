<?php

use Dybasedev\LunaPrototype\Content\Models\Content;
use Dybasedev\LunaPrototype\Content\Models\ContentChannel;
use Dybasedev\LunaPrototype\Content\Handlers\BaseContentHandler;
use Dybasedev\LunaPrototype\Content\Handlers\BaseChannelHandler;
use Dybasedev\LunaPrototype\Content\Handlers\HtmlContentHandler;
use Dybasedev\LunaPrototype\Content\Handlers\MarkdownContentHandler;
use Dybasedev\LunaPrototype\Content\Handlers\DefaultChannelHandler;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

// 测试用的 SessionHolder
class TestHandlerUser implements SessionHolder
{
    public function __construct(
        private int $id = 1,
        private string $role = 'admin'
    ) {}

    public function getOperatorId(): int
    {
        return $this->id;
    }

    public function getOperatorType(): int
    {
        return hash_code('test_user');
    }

    public function getOperatorTypeName(): string
    {
        return 'test_user';
    }

    public function getSessionHolderContext(): ?array
    {
        return ['role' => $this->role];
    }
}

// 测试用的自定义内容处理器
class TestContentHandler extends BaseContentHandler
{
    public function handlerName(): string
    {
        return '测试内容处理器';
    }

    public function handlerDescription(): string
    {
        return '用于测试的内容处理器';
    }

    public function render(Content $content, array $options = []): array
    {
        return [
            'id' => $content->id,
            'title' => strtoupper($content->title), // 转换为大写
            'content' => 'TEST: ' . $content->content,
            'custom' => 'test-rendered',
        ];
    }

    public function validationRules(): array
    {
        return [
            'test_field' => 'required|string',
        ];
    }

    public function beforeUpdate(Content $content, array $data): array
    {
        $data['payload']['processed'] = true;
        return $data;
    }
}

// 测试用的自定义频道处理器
class TestChannelHandler extends BaseChannelHandler
{
    public function handlerName(): string
    {
        return '测试频道处理器';
    }

    public function handlerDescription(): string
    {
        return '用于测试的频道处理器';
    }

    public function canPublish(Content $content, ContentChannel $channel, ?SessionHolder $publisher = null): bool
    {
        // 只有管理员可以发布
        if ($publisher && $publisher->getSessionHolderContext()['role'] === 'admin') {
            return true;
        }
        return false;
    }

    public function beforePublishToChannel(Content $content, ContentChannel $channel, array $pivotData = []): array
    {
        $pivotData['published_by'] = 'test-handler';
        return $pivotData;
    }
}

beforeEach(function () {
    // 运行迁移
    $migration = include __DIR__ . '/../../../src/Content/migrations/0001_01_01_000000_create_luna_prototype_content_tables.php';
    $migration->up();

    $this->lunaHandler = app(LunaHandler::class);
});

test('HTML内容处理器可以正确渲染', function () {
    $handler = new HtmlContentHandler();
    
    $content = Content::create([
        'name' => 'html-content',
        'title' => 'HTML内容',
        'payload' => [],
    ]);
    
    $content->createVersion('<h1>标题</h1><p>段落内容</p><script>alert("xss")</script>');
    
    $rendered = $handler->render($content->fresh());
    
    expect($rendered)->toHaveKey('content');
    expect($rendered['content'])->toContain('<h1>标题</h1>');
    expect($rendered['content'])->toContain('<p>段落内容</p>');
    expect($rendered['content'])->not->toContain('<script>'); // XSS应该被过滤
});

test('Markdown内容处理器可以正确渲染', function () {
    $handler = new MarkdownContentHandler();
    
    $content = Content::create([
        'name' => 'markdown-content',
        'title' => 'Markdown内容',
        'payload' => [],
    ]);
    
    $content->createVersion("# 标题\n\n这是一个**粗体**文本和*斜体*文本。");
    
    $rendered = $handler->render($content->fresh());
    
    expect($rendered)->toHaveKey('content');
    expect($rendered['content'])->toContain('<h1>标题</h1>');
    expect($rendered['content'])->toContain('<strong>粗体</strong>');
    expect($rendered['content'])->toContain('<em>斜体</em>');
    expect($rendered)->toHaveKey('raw_content');
});

test('内容处理器的批量处理', function () {
    $handler = new HtmlContentHandler();
    
    $contents = Collection::make();
    
    for ($i = 1; $i <= 3; $i++) {
        $content = Content::create([
            'name' => "batch-content-{$i}",
            'title' => "批量内容{$i}",
            'payload' => [],
        ]);
        $content->createVersion("<p>内容{$i}</p>");
        $contents->push($content->fresh());
    }
    
    $results = $handler->batchProcess($contents);
    
    expect($results)->toHaveCount(3);
    expect($results[0]['title'])->toBe('批量内容1');
    expect($results[1]['title'])->toBe('批量内容2');
    expect($results[2]['title'])->toBe('批量内容3');
});

test('自定义内容处理器的渲染和生命周期', function () {
    $handler = new TestContentHandler();
    
    $content = Content::create([
        'name' => 'custom-content',
        'title' => 'Custom Content',
        'handler_id' => 1,
        'payload' => [],
    ]);
    
    $content->createVersion('原始内容');
    
    // 测试渲染
    $rendered = $handler->render($content->fresh());
    
    expect($rendered['title'])->toBe('CUSTOM CONTENT'); // 应该被转换为大写
    expect($rendered['content'])->toBe('TEST: 原始内容');
    expect($rendered['custom'])->toBe('test-rendered');
    
    // 测试生命周期钩子
    $data = ['title' => '更新的内容', 'payload' => []];
    $data = $handler->beforeUpdate($content, $data);
    
    // TestContentHandler的beforeUpdate应该添加processed标记
    expect($data['payload']['processed'])->toBeTrue();
});

test('默认频道处理器的权限检查', function () {
    $handler = new DefaultChannelHandler();
    
    $content = Content::create([
        'name' => 'permission-content',
        'title' => '权限测试内容',
        'payload' => [],
    ]);
    
    $channel = ContentChannel::create([
        'id' => hash_code('default'),
        'name' => 'default',
        'display_name' => '默认频道',
        'handler_id' => 1,
        'config' => [],
    ]);
    
    $admin = new TestHandlerUser(1, 'admin');
    $user = new TestHandlerUser(2, 'user');
    
    // 默认处理器允许所有人发布
    expect($handler->canPublish($content, $channel, $admin))->toBeTrue();
    expect($handler->canPublish($content, $channel, $user))->toBeTrue();
    expect($handler->canPublish($content, $channel, null))->toBeTrue();
});

test('自定义频道处理器的权限和发布流程', function () {
    $handler = new TestChannelHandler();
    
    $content = Content::create([
        'name' => 'custom-channel-content',
        'title' => '自定义频道内容',
        'payload' => [],
    ]);
    
    $channel = ContentChannel::create([
        'id' => hash_code('custom'),
        'name' => 'custom',
        'display_name' => '自定义频道',
        'handler_id' => 1,
        'config' => [],
    ]);
    
    $admin = new TestHandlerUser(1, 'admin');
    $user = new TestHandlerUser(2, 'user');
    
    // 测试权限检查
    expect($handler->canPublish($content, $channel, $admin))->toBeTrue();
    expect($handler->canPublish($content, $channel, $user))->toBeFalse();
    
    // 测试发布前处理
    $pivotData = ['sort' => 10];
    $processed = $handler->beforePublishToChannel($content, $channel, $pivotData);
    
    expect($processed['sort'])->toBe(10);
    expect($processed['published_by'])->toBe('test-handler');
});

test('频道处理器的批量发布', function () {
    $handler = new DefaultChannelHandler();
    
    $channel = ContentChannel::create([
        'id' => hash_code('batch'),
        'name' => 'batch',
        'display_name' => '批量频道',
        'handler_id' => 1,
        'config' => [],
    ]);
    
    $contentIds = [];
    for ($i = 1; $i <= 3; $i++) {
        $content = Content::create([
            'name' => "batch-pub-{$i}",
            'title' => "批量发布{$i}",
            'payload' => [],
        ]);
        $contentIds[] = $content->id;
    }
    
    $publisher = new TestHandlerUser();
    $results = $handler->batchPublish($contentIds, $channel, $publisher);
    
    expect($results['success'])->toHaveCount(3);
    expect($results['failed'])->toBeEmpty();
    
    // 验证内容已附加到频道
    $channel->refresh();
    expect($channel->contents)->toHaveCount(3);
});

test('频道处理器的统计信息', function () {
    $handler = new DefaultChannelHandler();
    
    $channel = ContentChannel::create([
        'id' => hash_code('stats'),
        'name' => 'stats',
        'display_name' => '统计频道',
        'handler_id' => 1,
        'config' => [],
    ]);
    
    // 创建内容并附加到频道
    for ($i = 1; $i <= 5; $i++) {
        $content = Content::create([
            'name' => "stats-content-{$i}",
            'title' => "统计内容{$i}",
            'payload' => [],
            'published_at' => $i <= 3 ? now() : null, // 前3个已发布
            'views_count' => $i * 10,
        ]);
        $channel->contents()->attach($content->id);
    }
    
    $stats = $handler->getStatistics($channel);
    
    expect($stats['total_contents'])->toBe(5);
    expect($stats['published_contents'])->toBe(3);
    expect($stats['unpublished_contents'])->toBe(2);
    expect($stats['total_views'])->toBe(150); // 10+20+30+40+50
});

test('频道容量限制检查', function () {
    $handler = new DefaultChannelHandler();
    
    $channel = ContentChannel::create([
        'id' => hash_code('limited'),
        'name' => 'limited',
        'display_name' => '限制频道',
        'handler_id' => 1,
        'config' => ['max_contents' => 2],
    ]);
    
    // 添加2个内容
    for ($i = 1; $i <= 2; $i++) {
        $content = Content::create([
            'name' => "limited-content-{$i}",
            'title' => "限制内容{$i}",
            'payload' => [],
        ]);
        $channel->contents()->attach($content->id);
    }
    
    // 频道应该已满
    expect($handler->isChannelFull($channel))->toBeTrue();
    
    // 没有限制的频道不应该满
    $unlimitedChannel = ContentChannel::create([
        'id' => hash_code('unlimited'),
        'name' => 'unlimited',
        'display_name' => '无限频道',
        'handler_id' => 1,
        'config' => [],
    ]);
    
    expect($handler->isChannelFull($unlimitedChannel))->toBeFalse();
});

test('内容处理器的验证规则', function () {
    $handler = new TestContentHandler();
    
    $rules = $handler->validationRules();
    
    expect($rules)->toHaveKey('test_field');
    expect($rules['test_field'])->toBe('required|string');
    
    // 默认处理器没有额外验证规则
    $defaultHandler = new HtmlContentHandler();
    expect($defaultHandler->validationRules())->toBeEmpty();
});
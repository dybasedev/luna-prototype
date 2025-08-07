<?php

use Dybasedev\LunaPrototype\Content\Models\Content;
use Dybasedev\LunaPrototype\Content\Models\ContentVersion;
use Dybasedev\LunaPrototype\Content\Models\ContentChannel;
use Dybasedev\LunaPrototype\Content\Models\ContentCategory;
use Dybasedev\LunaPrototype\Content\Models\ContentMetadata;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// 测试用的 SessionHolder 实现
class TestContentUser implements SessionHolder
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
        return 'test_user';
    }

    public function getSessionHolderContext(): ?array
    {
        return ['test' => true];
    }
}

beforeEach(function () {
    $this->user = new TestContentUser();
    
    // 运行迁移
    $migration = include __DIR__ . '/../../../src/Content/migrations/0001_01_01_000000_create_luna_prototype_content_tables.php';
    $migration->up();
});

test('内容表结构正确创建', function () {
    expect(Schema::hasTable('luna_contents'))->toBeTrue();
    expect(Schema::hasColumns('luna_contents', [
        'id', 'owner_type', 'owner_id', 'name', 'title', 
        'keywords', 'description', 'handler_id', 'handler_config',
        'current_version_id', 'payload', 'published_at', 'views_count'
    ]))->toBeTrue();
});

test('可以创建内容', function () {
    $content = Content::create([
        'owner_type' => hash_code(get_class($this->user)),
        'owner_id' => $this->user->getOperatorId(),
        'name' => 'test-content',
        'title' => '测试内容',
        'keywords' => '测试,内容',
        'description' => '这是一个测试内容',
        'payload' => ['extra' => 'data'],
    ]);

    expect($content)->toBeInstanceOf(Content::class);
    expect($content->name)->toBe('test-content');
    expect($content->title)->toBe('测试内容');
    expect($content->owner_type)->toBe(hash_code(get_class($this->user)));
    expect($content->owner_id)->toBe(1);
    expect($content->payload)->toBe(['extra' => 'data']);
    expect($content->views_count)->toBe(0);
});

test('可以创建内容版本', function () {
    $content = Content::create([
        'name' => 'versioned-content',
        'title' => '有版本的内容',
        'payload' => [],
    ]);

    $version = $content->createVersion('这是内容的第一个版本', [
        'version_name' => '初始版本',
        'version_note' => '创建初始内容',
    ], $this->user);

    expect($version)->toBeInstanceOf(ContentVersion::class);
    expect($version->content)->toBe('这是内容的第一个版本');
    expect($version->version_name)->toBe('初始版本');
    expect($version->editor_type)->toBe(hash_code(get_class($this->user)));
    expect($version->editor_id)->toBe(1);
    
    // 刷新内容模型以获取更新
    $content->refresh();
    expect($content->current_version_id)->toBe($version->version_id);
    expect($content->currentVersion->version_id)->toBe($version->version_id);
});

test('可以切换内容版本', function () {
    $content = Content::create([
        'name' => 'multi-version-content',
        'title' => '多版本内容',
        'payload' => [],
    ]);

    $version1 = $content->createVersion('版本1的内容');
    $version2 = $content->createVersion('版本2的内容');
    $version3 = $content->createVersion('版本3的内容');

    // 应用版本2
    $result = $content->applyVersion($version2->version_id);
    expect($result)->toBeTrue();
    
    $content->refresh();
    expect($content->current_version_id)->toBe($version2->version_id);
    expect($content->content)->toBe('版本2的内容');
});

test('可以管理内容元数据', function () {
    $content = Content::create([
        'name' => 'metadata-content',
        'title' => '有元数据的内容',
        'payload' => [],
    ]);

    // 设置各种类型的元数据
    $content->setMetadata('author', '张三');
    $content->setMetadata('read_count', 100);
    $content->setMetadata('rating', 4.5);
    $content->setMetadata('featured', true);
    $content->setMetadata('tags', ['测试', '元数据']);
    $content->setMetadata('publish_date', '2023-12-01 10:00:00');

    // 获取元数据
    expect($content->getMetadata('author'))->toBe('张三');
    expect($content->getMetadata('read_count'))->toBe(100);
    expect($content->getMetadata('rating'))->toBe(4.5);
    expect($content->getMetadata('featured'))->toBe(true);
    expect($content->getMetadata('tags'))->toBe(['测试', '元数据']);
    expect($content->getMetadata('non_existent', 'default'))->toBe('default');
});

test('可以管理内容的发布状态', function () {
    $content = Content::create([
        'name' => 'publishable-content',
        'title' => '可发布的内容',
        'payload' => [],
    ]);

    // 初始状态未发布
    expect($content->isPublished())->toBeFalse();
    expect($content->published_at)->toBeNull();

    // 发布内容
    $publishTime = now();
    $content->publish($publishTime);
    expect($content->fresh()->isPublished())->toBeTrue();
    expect($content->fresh()->published_at->format('Y-m-d H:i:s'))->toBe($publishTime->format('Y-m-d H:i:s'));

    // 取消发布
    $content->unpublish();
    expect($content->fresh()->isPublished())->toBeFalse();
    expect($content->fresh()->published_at)->toBeNull();
});

test('可以增加浏览次数', function () {
    $content = Content::create([
        'name' => 'viewable-content',
        'title' => '可浏览的内容',
        'payload' => [],
    ]);

    expect($content->views_count)->toBe(0);

    $content->incrementViews();
    expect($content->fresh()->views_count)->toBe(1);

    $content->incrementViews(5);
    expect($content->fresh()->views_count)->toBe(6);
});

test('可以管理内容与频道的关系', function () {
    $content = Content::create([
        'name' => 'channel-content',
        'title' => '频道内容',
        'payload' => [],
    ]);

    $channel = ContentChannel::create([
        'id' => hash_code('news'),
        'name' => 'news',
        'display_name' => '新闻频道',
        'handler_id' => 1,
        'config' => [],
    ]);

    // 附加到频道
    $content->attachToChannel($channel, ['sort' => 10]);
    
    $content->refresh();
    expect($content->channels)->toHaveCount(1);
    expect($content->channels->first()->id)->toBe($channel->id);
    expect($content->channels->first()->pivot->sort)->toBe(10);

    // 从频道移除
    $content->detachFromChannel($channel);
    
    $content->refresh();
    expect($content->channels)->toHaveCount(0);
});

test('可以管理内容与分类的关系', function () {
    $content = Content::create([
        'name' => 'categorized-content',
        'title' => '分类内容',
        'payload' => [],
    ]);

    $category1 = ContentCategory::create([
        'name' => 'tech',
        'display_name' => '科技',
    ]);

    $category2 = ContentCategory::create([
        'name' => 'news',
        'display_name' => '新闻',
    ]);

    // 附加到分类
    $content->attachToCategory($category1, ['sort' => 1]);
    $content->attachToCategory($category2, ['sort' => 2]);
    
    $content->refresh();
    expect($content->categories)->toHaveCount(2);
    expect($content->categories->pluck('name')->toArray())->toBe(['tech', 'news']);

    // 从分类移除
    $content->detachFromCategory($category1);
    
    $content->refresh();
    expect($content->categories)->toHaveCount(1);
    expect($content->categories->first()->name)->toBe('news');
});

test('可以按名称查找内容', function () {
    Content::create([
        'name' => 'unique-content-name',
        'title' => '唯一内容',
        'payload' => [],
    ]);

    $found = Content::findByName('unique-content-name');
    expect($found)->toBeInstanceOf(Content::class);
    expect($found->title)->toBe('唯一内容');

    $notFound = Content::findByName('non-existent');
    expect($notFound)->toBeNull();
});

test('已发布和未发布的查询作用域', function () {
    // 创建已发布的内容
    $published1 = Content::create([
        'name' => 'published-1',
        'title' => '已发布1',
        'payload' => [],
        'published_at' => now()->subDays(1),
    ]);

    $published2 = Content::create([
        'name' => 'published-2',
        'title' => '已发布2',
        'payload' => [],
        'published_at' => now()->subHours(1),
    ]);

    // 创建未发布的内容
    $unpublished1 = Content::create([
        'name' => 'unpublished-1',
        'title' => '未发布1',
        'payload' => [],
        'published_at' => null,
    ]);

    $unpublished2 = Content::create([
        'name' => 'unpublished-2',
        'title' => '未发布2',
        'payload' => [],
        'published_at' => now()->addDays(1), // 未来时间
    ]);

    // 测试已发布查询
    $publishedContents = Content::published()->get();
    expect($publishedContents)->toHaveCount(2);
    expect($publishedContents->pluck('name')->toArray())->toContain('published-1', 'published-2');

    // 测试未发布查询
    $unpublishedContents = Content::unpublished()->get();
    expect($unpublishedContents)->toHaveCount(2);
    expect($unpublishedContents->pluck('name')->toArray())->toContain('unpublished-1', 'unpublished-2');
});

test('内容的关联关系正确加载', function () {
    $content = Content::create([
        'owner_type' => hash_code(get_class($this->user)),
        'owner_id' => $this->user->getOperatorId(),
        'name' => 'related-content',
        'title' => '关联内容',
        'payload' => [],
    ]);

    // 创建版本
    $version = $content->createVersion('内容版本');

    // 创建元数据
    $metadata = $content->setMetadata('key1', 'value1');

    // 测试关联关系
    expect($content->versions)->toHaveCount(1);
    expect($content->metadata)->toHaveCount(1);
    expect($content->currentVersion->version_id)->toBe($version->version_id);
});
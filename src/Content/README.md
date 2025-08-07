# Luna Content 组件

Luna Content 是一个功能完善的内容管理系统组件，提供了内容创建、版本管理、分类组织、多频道发布等全方位的内容管理功能。

## 功能特性

- ✅ **内容管理**：创建、编辑、删除各类内容
- ✅ **版本控制**：支持内容多版本管理，可追溯历史变更
- ✅ **多频道发布**：一份内容可发布到多个频道
- ✅ **分类体系**：支持无限层级的分类管理
- ✅ **附件管理**：支持文件上传和远程文件引用
- ✅ **元数据扩展**：灵活的元数据系统，支持各种类型的扩展数据
- ✅ **处理器机制**：可扩展的内容和频道处理器
- ✅ **搜索功能**：支持关键词搜索和多维度过滤
- ✅ **统计分析**：提供内容、频道、分类的统计数据

## 核心概念

### 内容（Content）
内容是系统的核心实体，包含标题、正文、关键词等基本信息，支持版本管理和元数据扩展。

### 频道（Channel）
频道是内容的发布渠道，每个频道可以有自己的处理器来控制内容的发布流程。

### 分类（Category）
分类用于组织内容，支持多级分类结构，一个内容可以属于多个分类。

### 版本（Version）
每次内容修改都会创建新版本，可以查看历史版本并回滚。

### 处理器（Handler）
- **内容处理器**：负责内容的渲染、验证、格式化等
- **频道处理器**：控制内容发布到频道的流程和权限

## 快速开始

### 1. 安装配置

在 `AppServiceProvider` 中注册模块：

```php
use Dybasedev\LunaPrototype\Content\LunaContentConfigure;

public function register(): void
{
    parent::register();
    
    $this->registerModule(
        LunaContentConfigure::create()
            ->build()
    );
}
```

### 2. 发布并运行迁移

```bash
# 发布迁移文件
php artisan vendor:publish --provider="Dybasedev\LunaPrototype\Content\LunaContentServiceProvider" --tag=luna-content-migrations

# 运行迁移
php artisan migrate
```

### 3. 注册处理器

```php
use Dybasedev\LunaPrototype\Foundation\LunaHandlerConfigure;
use Dybasedev\LunaPrototype\Content\Handlers\ArticleContentHandler;
use Dybasedev\LunaPrototype\Content\Handlers\DefaultChannelHandler;

$this->extendModule(function() {
    return LunaHandlerConfigure::create()
        ->group('content-handlers', '内容处理器', function($register) {
            $register->handler(ArticleContentHandler::class);
        })
        ->group('channel-handlers', '频道处理器', function($register) {
            $register->handler(DefaultChannelHandler::class);
        })
        ->build();
});
```

## 使用示例

### 创建内容

```php
$content = luna_content()->createContent([
    'name' => 'hello-world',
    'title' => 'Hello World',
    'description' => '第一篇文章',
    'keywords' => 'hello, world',
    'content' => '<p>这是文章内容</p>',
    'handler_id' => $articleHandlerId,
], $currentUser);
```

### 创建频道

```php
$channel = luna_content()->createOrUpdateChannel('news', [
    'display_name' => '新闻频道',
    'description' => '发布最新新闻',
    'handler_id' => $channelHandlerId,
    'is_active' => true,
]);
```

### 发布内容到频道

```php
$success = luna_content()->publishToChannel(
    $content,
    'news',
    ['sort' => 1],
    $currentUser
);
```

### 版本管理

```php
// 创建新版本
$version = $content->createVersion(
    '<p>更新后的内容</p>',
    ['version_name' => 'v2.0'],
    $currentUser
);

// 查看版本历史
$versions = $content->versions;

// 回滚到指定版本
$content->applyVersion($version->version_id);
```

### 分类管理

```php
// 创建分类
$category = luna_content()->createCategory([
    'name' => 'tech',
    'display_name' => '科技',
    'parent_id' => 0,
]);

// 将内容添加到分类
$content->attachToCategory($category);

// 获取分类树
$tree = luna_content()->getCategoryTree();
```

### 搜索内容

```php
$results = luna_content()->searchContents('关键词', [
    'channel_id' => $channelId,
    'category_id' => $categoryId,
    'published' => true,
])->paginate(20);
```

### 渲染内容

```php
// 使用处理器渲染内容
$rendered = luna_content()->renderContent($content, [
    'include_attachments' => true,
    'summary_length' => 300,
]);
```

## 高级功能

### 自定义内容处理器

```php
use Dybasedev\LunaPrototype\Content\Handlers\BaseContentHandler;

class VideoContentHandler extends BaseContentHandler
{
    public function handlerName(): string
    {
        return '视频内容处理器';
    }
    
    public function render(Content $content, array $options = []): array
    {
        // 自定义渲染逻辑
        return [
            'id' => $content->id,
            'title' => $content->title,
            'video_url' => $content->payload['video_url'] ?? null,
            'duration' => $this->getVideoDuration($content),
            // ...
        ];
    }
    
    public function validationRules(): array
    {
        return [
            'payload.video_url' => 'required|url',
            'payload.duration' => 'required|integer|min:1',
        ];
    }
}
```

### 自定义频道处理器

```php
use Dybasedev\LunaPrototype\Content\Handlers\BaseChannelHandler;

class ReviewChannelHandler extends BaseChannelHandler
{
    public function canPublish(Content $content, ContentChannel $channel, ?SessionHolder $publisher = null): bool
    {
        // 检查内容是否已审核
        if ($content->getMetadata('reviewed') !== true) {
            return false;
        }
        
        return parent::canPublish($content, $channel, $publisher);
    }
    
    public function afterPublishToChannel(Content $content, ContentChannel $channel): void
    {
        // 发送通知
        event('content.published', [$content, $channel]);
    }
}
```

### 元数据管理

```php
// 设置元数据
$content->setMetadata('author', '张三');
$content->setMetadata('source', '原创');
$content->setMetadata('tags', ['科技', '互联网']);

// 获取元数据
$author = $content->getMetadata('author');
$tags = $content->getMetadata('tags', []);

// 批量设置
ContentMetadata::batchSetFor($content->id, [
    'view_count' => 100,
    'like_count' => 50,
    'comment_count' => 10,
]);
```

### 附件管理

```php
// 上传附件
$attachment = luna_content()->uploadAttachment($request->file('image'), [
    'name' => '产品图片',
    'disk' => 'public',
]);

// 从URL创建附件
$attachment = luna_content()->createAttachmentFromUrl(
    'https://example.com/image.jpg',
    ['name' => '远程图片']
);

// 关联到内容
$content->attachments()->save($attachment);
```

### 统计分析

```php
// 获取内容统计
$stats = luna_content()->getContentStatistics([
    'date_from' => now()->subMonth(),
    'date_to' => now(),
]);

// 获取频道统计
$channelStats = luna_content()->getChannelHandler($channel)->getStatistics($channel);
```

## 配置选项

```php
LunaContentConfigure::create()
    // 自定义模型
    ->useContentModel(CustomContent::class)
    ->useChannelModel(CustomChannel::class)
    
    // 功能开关
    ->withoutVersioning()      // 禁用版本控制
    ->withoutCategories()      // 禁用分类功能
    ->withoutAttachments()     // 禁用附件功能
    
    // 处理器组配置
    ->contentHandlerGroup('my-content-handlers')
    ->channelHandlerGroup('my-channel-handlers')
    
    ->build();
```

## 最佳实践

1. **合理使用处理器**：为不同类型的内容创建专门的处理器
2. **版本控制**：重要内容建议启用版本控制
3. **缓存优化**：对频繁访问的内容使用缓存
4. **权限控制**：结合 Permission 组件实现细粒度权限控制
5. **元数据设计**：合理规划元数据结构，避免过度使用

## 相关组件

- **Foundation**：提供基础架构支持
- **Permission**：实现内容访问权限控制
- **AssetsAccount**：管理付费内容
- **Trade**：实现内容交易功能
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
        ->group('content', '内容处理器', function($register) {
            // 注册内容处理器（Pure Handler）
            $register->handler(ArticleContentHandler::class, 'article');
            $register->handler(HtmlContentHandler::class, 'html');
            $register->handler(MarkdownContentHandler::class, 'markdown');
            // 注册自定义处理器
            $register->handler(VideoContentHandler::class, 'video');
        })
        ->group('channel', '频道处理器', function($register) {
            // 注册频道处理器
            $register->handler(DefaultChannelHandler::class, 'default-channel');
            $register->handler(ReviewChannelHandler::class, 'review-channel');
        })
        ->build();
});
```

#### 关于 Pure Handler

Content 组件的处理器是 "pure" 的，这意味着：
- 处理器类本身不需要在数据库中创建实体记录
- 处理器的 ID 是其类名的 hash code
- 可以直接通过类名、别名或 ID 引用处理器

```php
// 推荐使用别名（最简洁、最稳定）
$content->handler('article');
$content->handler('html');
$content->handler('markdown');

// 如果需要使用类名，建议使用 ::class 常量
$content->handler(ArticleContentHandler::class);

// 也可以使用 ID（不推荐，除非特殊场景）
$content->handler(hash_code(ArticleContentHandler::class));
```

## 使用示例

### 处理器使用最佳实践

在使用处理器时，推荐遵循以下最佳实践：

```php
// ✅ 推荐：使用别名（最简洁、最稳定）
$content->handler('content.default');
$content->handler('content.video');
$content->handler('content.article');

// ✅ 推荐：如果需要使用类名，使用 ::class 常量
use Dybasedev\LunaPrototype\Content\Handlers\DefaultContentHandler;
$content->handler(DefaultContentHandler::class);

// ❌ 避免：硬编码类名字符串
$content->handler('Dybasedev\\LunaPrototype\\Content\\Handlers\\DefaultContentHandler');

// ❌ 避免：直接使用 hash code（除非特殊场景）
$content->handler(123456789);
```

为什么推荐使用别名？
- **简洁性**：别名更短，代码更易读
- **稳定性**：类名可能重构，但别名通常保持不变
- **安全性**：避免拼写错误和命名空间问题
- **一致性**：遵循 Laravel 生态系统的命名约定（小写字母+短横线）

### Builder 模式（推荐）

Content 组件提供了强大的 Builder 模式，支持链式调用、数据验证和处理器集成：

#### 创建内容

使用 Builder 创建内容（推荐方式）：

```php
// 链式调用创建内容
$content = luna_content()->newContent()
    ->name('hello-world')
    ->title('Hello World')
    ->description('第一篇文章')
    ->keywords('hello, world')
    ->handler('article') // 使用别名（推荐）
    ->owner($currentUser)
    ->content('<p>这是文章内容</p>')
    ->versionName('初始版本')
    ->addCategory(1)
    ->addChannel(1)
    ->publish() // 立即发布
    ->save();

// 从 HTTP 请求创建
$content = ContentBuilder::fromRequest($request)
    ->owner($currentUser)
    ->validate() // 验证数据
    ->save();

// 创建草稿
$draft = luna_content()->newContent()
    ->name('draft-article')
    ->title('草稿文章')
    ->draft() // 设置为草稿
    ->save();
```

#### 更新内容

```php
// 链式调用更新内容
$updated = luna_content()->editContent($content)
    ->title('更新后的标题')
    ->description('更新后的描述')
    ->content('更新后的内容', 'v2.0', '重要更新')
    ->addCategory(3) // 添加新分类
    ->removeCategory(1) // 移除分类
    ->editor($currentUser)
    ->save();

// 从请求更新
$updated = ContentUpdateBuilder::fromRequest($request, $content)
    ->editor($currentUser)
    ->validate()
    ->save();

// 只更新特定字段
$updated = luna_content()->editContent($content)
    ->title('只更新标题')
    ->save();
```

#### 创建频道

```php
// 使用 Builder 创建频道
$channel = luna_content()->newChannel()
    ->name('news')
    ->displayName('新闻频道')
    ->description('发布最新新闻')
    ->handler($channelHandlerId)
    ->config(['auto_publish' => true])
    ->setConfig('max_items', 100) // 设置特定配置项
    ->activate()
    ->save();

// 从请求创建频道
$channel = ChannelBuilder::fromRequest($request)
    ->validate()
    ->save();
```

#### 数据验证

Builder 集成了数据验证，支持基础验证和处理器特定的验证规则：

```php
try {
    $content = luna_content()->newContent()
        ->title('缺少必需的 name 字段')
        ->validate() // 验证失败，抛出 ValidationException
        ->save();
} catch (\Illuminate\Validation\ValidationException $e) {
    $errors = $e->errors();
    // 处理验证错误
}

// 处理器验证
$content = luna_content()->newContent()
    ->name('article')
    ->title('文章')
    ->handler('article') // 设置处理器后，验证会包含处理器的规则
    ->payload(['author' => 'John']) // 如果处理器要求特定的 payload 结构
    ->validate()
    ->save();
```

#### Builder 模式的优势

- ✅ **链式调用**：流畅的 API，代码更易读
- ✅ **灵活构建**：可以根据条件动态构建
- ✅ **数据验证**：集成验证机制，包括处理器验证
- ✅ **IDE 支持**：完整的类型提示和自动补全
- ✅ **预览支持**：使用 `build()` 方法可以只构建不保存

### 传统方式（向后兼容）

如果你更喜欢传统的数组参数方式，仍然可以使用：

```php
// 创建内容
$content = luna_content()->createContent([
    'name' => 'hello-world',
    'title' => 'Hello World',
    'description' => '第一篇文章',
    'keywords' => 'hello, world',
    'content' => '<p>这是文章内容</p>',
    'handler_id' => $articleHandlerId,
], $currentUser);

// 更新内容
$content = luna_content()->updateContent($content, [
    'title' => '更新后的标题',
    'description' => '更新后的描述',
], $currentUser);

// 创建频道
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
// 使用处理器渲染内容，返回 ContentResult 对象
$result = luna_content()->renderContent($content, [
    'strip_tags' => true,
    'max_length' => 500,
]);

// ContentResult 提供了丰富的访问方法
echo $result->getTitle();
echo $result->getContent();
echo $result->getSummary(200); // 获取摘要
echo $result->getViewsCount();

// 检查是否已发布
if ($result->isPublished()) {
    echo '发布时间：' . $result->getPublishedAt()->format('Y-m-d H:i:s');
}

// 获取关联数据
$categories = $result->getCategories();
$channels = $result->getChannels();
$metadata = $result->getMetadata();

// 获取自定义字段
$customValue = $result->getCustomField('raw_markdown');

// 转换为数组或 JSON
$array = $result->toArray();
$json = $result->toJson();
```

## 高级功能

### 关于内容处理器

Content 组件默认只提供一个基础的 `DefaultContentHandler`，它支持纯文本和基本的 HTML 内容处理。如果你需要处理特定格式的内容（如 Markdown、富文本编辑器等），建议创建自定义处理器。

#### 为什么不内置更多处理器？

1. **减少依赖**：避免引入不必要的第三方库，保持组件轻量
2. **灵活选择**：不同项目可能需要不同的 Markdown 解析器或富文本处理库
3. **版本控制**：让业务端自行控制第三方库的版本

#### 推荐的第三方库

如果需要处理特定格式的内容，可以考虑以下库：

- **Markdown**: `league/commonmark`、`erusev/parsedown`
- **HTML 净化**: `ezyang/htmlpurifier`、`mews/purifier`
- **富文本**: `froala/wysiwyg-editor`、`tinymce/tinymce`

### 自定义内容处理器

创建自定义处理器并注册：

```php
use Dybasedev\LunaPrototype\Content\Handlers\BaseContentHandler;

class VideoContentHandler extends BaseContentHandler
{
    public function handlerName(): string
    {
        return '视频内容处理器';
    }
    
    public function handlerDescription(): string
    {
        return '处理视频类型的内容，支持视频播放、时长统计等功能';
    }
    
    public function render(Content $content, array $options = []): array
    {
        // 自定义渲染逻辑
        return [
            'id' => $content->id,
            'title' => $content->title,
            'video_url' => $content->payload['video_url'] ?? null,
            'duration' => $this->getVideoDuration($content),
            'thumbnail' => $this->getThumbnail($content),
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
    
    private function getVideoDuration(Content $content): ?int
    {
        return $content->payload['duration'] ?? null;
    }
    
    private function getThumbnail(Content $content): ?string
    {
        return $content->payload['thumbnail'] ?? null;
    }
}

// 在服务提供者中注册处理器
$this->extendModule(function() {
    return LunaHandlerConfigure::create()
        ->group('content', '内容处理器', function($register) {
            $register->handler(VideoContentHandler::class, 'video');
        })
        ->build();
});

// 使用自定义处理器
$video = luna_content()->newContent()
    ->name('my-first-video')
    ->title('我的第一个视频')
    ->handler('video') // 使用别名
    ->payload([
        'video_url' => 'https://example.com/video.mp4',
        'duration' => 300,
        'thumbnail' => 'https://example.com/thumbnail.jpg'
    ])
    ->save();
```

### 自定义频道处理器

```php
use Dybasedev\LunaPrototype\Content\Handlers\BaseChannelHandler;

class ReviewChannelHandler extends BaseChannelHandler
{
    public function handlerName(): string
    {
        return '审核频道处理器';
    }
    
    public function handlerDescription(): string
    {
        return '需要审核通过才能发布内容的频道处理器';
    }
    
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

// 注册频道处理器
$this->extendModule(function() {
    return LunaHandlerConfigure::create()
        ->group('channel', '频道处理器', function($register) {
            $register->handler(ReviewChannelHandler::class, 'review-channel');
        })
        ->build();
});

// 创建使用该处理器的频道
$reviewChannel = luna_content()->newChannel()
    ->name('review-articles')
    ->displayName('待审核文章')
    ->handler('review-channel') // 使用别名
    ->save();

// 尝试发布内容到频道
$content->setMetadata('reviewed', true); // 标记为已审核
$success = luna_content()->publishToChannel($content, 'review-articles');
```

### 使用第三方库的处理器示例

以下是一个使用 `league/commonmark` 处理 Markdown 的示例：

```php
// 首先安装依赖：composer require league/commonmark

use Dybasedev\LunaPrototype\Content\Handlers\BaseContentHandler;
use Dybasedev\LunaPrototype\Content\Results\ContentResult;
use League\CommonMark\CommonMarkConverter;

class MarkdownContentHandler extends BaseContentHandler
{
    private CommonMarkConverter $converter;
    
    public function __construct()
    {
        $this->converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
    
    public function handlerName(): string
    {
        return 'Markdown 内容处理器';
    }
    
    public function handlerDescription(): string
    {
        return '使用 CommonMark 解析 Markdown 内容';
    }
    
    public function render(Content $content, array $options = []): ContentResult
    {
        $result = new ContentResult();
        
        // 设置基础字段
        $result->setId($content->id)
            ->setName($content->name)
            ->setTitle($content->title)
            ->setDescription($content->description)
            ->setContent($this->converter->convert($content->content ?? ''))
            ->setPayload($content->payload)
            ->setPublishedAt($content->published_at)
            ->setCreatedAt($content->created_at)
            ->setUpdatedAt($content->updated_at);
        
        // 保存原始 Markdown 作为自定义字段
        $result->setCustomField('raw_markdown', $content->content);
        
        return $result;
    }
    
    public function validationRules(): array
    {
        return [
            'content' => 'nullable|string|max:50000',
        ];
    }
    
    public function validateContent(array $data): array
    {
        $errors = [];
        
        // 可以添加 Markdown 特定的验证
        if (isset($data['content'])) {
            // 检查是否包含危险的 HTML 标签
            if (preg_match('/<script|<iframe|javascript:/i', $data['content'])) {
                $errors['content'] = '内容包含不允许的标签';
            }
        }
        
        return $errors;
    }
}

// 注册处理器
$this->extendModule(function() {
    return LunaHandlerConfigure::create()
        ->group('content', '内容处理器', function($register) {
            $register->handler(MarkdownContentHandler::class, 'content.markdown');
        })
        ->build();
});
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
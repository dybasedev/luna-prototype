<?php

use Dybasedev\LunaPrototype\Content\LunaContent;
use Dybasedev\LunaPrototype\Content\Builders\ContentBuilder;
use Dybasedev\LunaPrototype\Content\Builders\ContentUpdateBuilder;
use Dybasedev\LunaPrototype\Content\Builders\ChannelBuilder;

// 获取 LunaContent 实例
$lunaContent = app(LunaContent::class);

// 示例1：使用 Builder 创建内容（链式调用）
$content = $lunaContent->newContent()
    ->name('my-article')
    ->title('我的文章')
    ->description('这是一篇关于 Laravel 的文章')
    ->keywords('laravel,php,web开发')
    ->handlerId(1) // 设置处理器
    ->payload(['author' => 'John Doe'])
    ->owner($user)
    ->content('文章的详细内容...')
    ->versionName('初始版本')
    ->addCategory(1)
    ->addCategory(2)
    ->addChannel(1)
    ->publish() // 立即发布
    ->save(); // 保存并返回创建的内容

// 示例2：从请求创建内容
$content = ContentBuilder::fromRequest($request)
    ->owner($user)
    ->validate() // 验证数据
    ->save();

// 示例3：构建但不保存（用于预览）
$preview = $lunaContent->newContent()
    ->name('preview-article')
    ->title('预览文章')
    ->content('预览内容...')
    ->build(); // 返回数组，不保存到数据库

// 示例4：更新内容（链式调用）
$updated = $lunaContent->editContent($content)
    ->title('更新后的标题')
    ->description('更新后的描述')
    ->content('更新后的内容...', 'v2.0', '重要更新')
    ->addCategory(3) // 添加新分类
    ->removeCategory(1) // 移除分类
    ->editor($user)
    ->save();

// 示例5：从请求更新内容
$updated = ContentUpdateBuilder::fromRequest($request, $content)
    ->editor($user)
    ->validate()
    ->save();

// 示例6：只更新特定字段
$updated = $lunaContent->editContent($content)
    ->title('只更新标题')
    ->save();

// 示例7：创建草稿
$draft = $lunaContent->newContent()
    ->name('draft-article')
    ->title('草稿文章')
    ->draft() // 设置为草稿
    ->save();

// 示例8：创建频道
$channel = $lunaContent->newChannel()
    ->name('tech-news')
    ->displayName('技术新闻')
    ->description('最新的技术新闻和动态')
    ->handlerId(2)
    ->config(['auto_publish' => true])
    ->setConfig('max_items', 100) // 设置特定配置项
    ->activate()
    ->sort(10)
    ->save();

// 示例9：从请求创建频道
$channel = ChannelBuilder::fromRequest($request)
    ->validate()
    ->save();

// 示例10：数据验证
try {
    $content = $lunaContent->newContent()
        ->title('缺少必需的 name 字段') // 故意不设置 name
        ->validate() // 这里会抛出 ValidationException
        ->save();
} catch (\Illuminate\Validation\ValidationException $e) {
    $errors = $e->errors();
    // 处理验证错误
}

// 示例11：使用处理器验证
// 假设 ArticleContentHandler 对 payload.author 有特殊要求
$content = $lunaContent->newContent()
    ->name('article-with-handler')
    ->title('带处理器的文章')
    ->handlerId($articleHandlerId) // 设置处理器后，验证规则会包含处理器的规则
    ->payload(['author' => '']) // 如果处理器要求 author 不能为空，这里会验证失败
    ->validate()
    ->save();

// 示例12：批量操作
$contents = [];
for ($i = 1; $i <= 10; $i++) {
    $contents[] = $lunaContent->newContent()
        ->name("batch-article-{$i}")
        ->title("批量文章 {$i}")
        ->description("这是第 {$i} 篇批量创建的文章")
        ->categoryIds([1, 2])
        ->publish()
        ->save();
}

// 示例13：复杂的内容更新
$content = $lunaContent->editContent($existingContent)
    ->title('新标题')
    ->mergePayload(['new_field' => 'new_value']) // 合并载荷数据
    ->content('完全重写的内容', 'v3.0', '重大改版')
    ->categoryIds([3, 4, 5]) // 完全替换分类
    ->publish() // 发布内容
    ->editor($admin)
    ->save();

// 示例14：条件构建
$builder = $lunaContent->newContent()
    ->name('conditional-article')
    ->title('条件文章');

if ($request->has('featured')) {
    $builder->addCategory($featuredCategoryId);
}

if ($user->isAdmin()) {
    $builder->publish();
} else {
    $builder->draft();
}

$content = $builder->save();
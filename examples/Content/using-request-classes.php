<?php

use Dybasedev\LunaPrototype\Content\LunaContent;
use Dybasedev\LunaPrototype\Content\ContentCreationRequest;
use Dybasedev\LunaPrototype\Content\ContentUpdateRequest;
use Dybasedev\LunaPrototype\Content\ChannelCreationRequest;

// 获取 LunaContent 实例
$lunaContent = app(LunaContent::class);

// 示例1：使用 ContentCreationRequest 创建内容
$createRequest = new ContentCreationRequest(
    name: 'my-article',
    title: '我的文章',
    description: '这是一篇关于 Laravel 的文章',
    keywords: 'laravel,php,web开发',
    handlerId: 1, // 假设 ID 为 1 的处理器存在
    handlerConfig: ['template' => 'default'],
    payload: ['author' => 'John Doe'],
    owner: $user, // 假设 $user 是实现了 SessionHolder 的用户实例
    content: '文章的详细内容...',
    versionName: '初始版本',
    versionNote: '创建文章',
    categoryIds: [1, 2], // 分类 ID
    channelIds: [1], // 频道 ID
    published: true, // 立即发布
);

$content = $lunaContent->createContent($createRequest);

// 示例2：使用 ContentUpdateRequest 更新内容
$updateRequest = new ContentUpdateRequest(
    title: '更新后的文章标题',
    description: '更新后的描述',
    keywords: 'laravel,php,tutorial',
    // 只更新提供的字段，其他字段保持不变
    content: '更新后的文章内容...',
    versionName: 'v2.0',
    versionNote: '更新了内容和标题',
    editor: $user,
    categoryIds: [1, 2, 3], // 更新分类
    published: true, // 确保已发布
);

$updatedContent = $lunaContent->updateContent($content, $updateRequest);

// 示例3：使用 ChannelCreationRequest 创建频道
$channelRequest = new ChannelCreationRequest(
    name: 'tech-news',
    displayName: '技术新闻',
    description: '最新的技术新闻和动态',
    handlerId: 2, // 频道处理器 ID
    config: [
        'auto_publish' => true,
        'max_items' => 100,
    ],
    isActive: true,
    sort: 10,
);

$channel = $lunaContent->createOrUpdateChannel($channelRequest);

// 示例4：向后兼容 - 仍然可以使用数组（但不推荐）
$content = $lunaContent->createContent([
    'name' => 'legacy-article',
    'title' => '使用旧方式创建的文章',
    'description' => '这种方式仍然有效，但不推荐',
    'payload' => [],
    'published' => true,
], $user);

// 示例5：部分更新 - 只更新某些字段
$partialUpdate = new ContentUpdateRequest(
    title: '只更新标题',
    // 其他字段都是 null，不会被更新
);

$lunaContent->updateContent($content, $partialUpdate);

// 示例6：创建草稿（未发布）
$draftRequest = new ContentCreationRequest(
    name: 'draft-article',
    title: '草稿文章',
    description: '这是一个草稿',
    payload: [],
    owner: $user,
    published: false, // 不立即发布
);

$draft = $lunaContent->createContent($draftRequest);
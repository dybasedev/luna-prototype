<?php

use Dybasedev\LunaPrototype\Content\Models\Content;
use Dybasedev\LunaPrototype\Content\Models\ContentCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// 使用 RefreshDatabase trait，不需要手动运行迁移

test('可以创建分类', function () {
    $category = ContentCategory::create([
        'name' => 'technology',
        'display_name' => '科技',
        'description' => '科技相关内容',
        'icon' => 'tech-icon',
        'sort' => 10,
    ]);

    expect($category)->toBeInstanceOf(ContentCategory::class);
    expect($category->name)->toBe('technology');
    expect($category->display_name)->toBe('科技');
    expect($category->parent_id)->toBe(0);
    expect($category->is_active)->toBeTrue();
});

test('可以创建分类层级结构', function () {
    // 创建父分类
    $parent = ContentCategory::create([
        'name' => 'parent',
        'display_name' => '父分类',
    ]);

    // 创建子分类
    $child1 = ContentCategory::create([
        'parent_id' => $parent->id,
        'name' => 'child1',
        'display_name' => '子分类1',
    ]);

    $child2 = ContentCategory::create([
        'parent_id' => $parent->id,
        'name' => 'child2',
        'display_name' => '子分类2',
    ]);

    // 创建孙分类
    $grandchild = ContentCategory::create([
        'parent_id' => $child1->id,
        'name' => 'grandchild',
        'display_name' => '孙分类',
    ]);

    // 测试父子关系
    expect($parent->children)->toHaveCount(2);
    expect($parent->children->pluck('name')->toArray())->toContain('child1', 'child2');
    
    expect($child1->parent->id)->toBe($parent->id);
    expect($child1->children)->toHaveCount(1);
    expect($child1->children->first()->name)->toBe('grandchild');
    
    expect($grandchild->parent->id)->toBe($child1->id);
});

test('分类名称必须全局唯一', function () {
    $parent = ContentCategory::create([
        'name' => 'unique-parent',
        'display_name' => '唯一父级',
    ]);

    ContentCategory::create([
        'parent_id' => $parent->id,
        'name' => 'duplicate-name',
        'display_name' => '重复名称1',
    ]);

    // 尝试创建同名分类应该失败（即使在不同父级下）
    expect(fn() => ContentCategory::create([
        'parent_id' => $parent->id,
        'name' => 'duplicate-name',
        'display_name' => '重复名称2',
    ]))->toThrow(\Illuminate\Database\QueryException::class);

    // 在不同父级下也不能有同名分类
    $anotherParent = ContentCategory::create([
        'name' => 'another-parent',
        'display_name' => '另一个父级',
    ]);

    expect(fn() => ContentCategory::create([
        'parent_id' => $anotherParent->id,
        'name' => 'duplicate-name',
        'display_name' => '不同父级下的同名分类',
    ]))->toThrow(\Illuminate\Database\QueryException::class);

    // 验证 NamedId trait 的功能
    expect($parent->id)->toBe(hash_code('unique-parent'));
});

test('可以管理分类的内容', function () {
    $category = ContentCategory::create([
        'name' => 'content-category',
        'display_name' => '内容分类',
    ]);

    // 创建几个内容
    $content1 = Content::create([
        'name' => 'cat-content-1',
        'title' => '分类内容1',
        'payload' => [],
    ]);

    $content2 = Content::create([
        'name' => 'cat-content-2',
        'title' => '分类内容2',
        'payload' => [],
        'published_at' => now(),
    ]);

    // 将内容添加到分类
    $category->contents()->attach($content1->id, ['sort' => 1]);
    $category->contents()->attach($content2->id, ['sort' => 2]);

    // 测试关联
    expect($category->contents)->toHaveCount(2);
    expect($category->contents->pluck('name')->toArray())->toBe(['cat-content-1', 'cat-content-2']);

    // 测试已发布内容
    expect($category->publishedContents()->get())->toHaveCount(1);
    expect($category->publishedContents()->first()->name)->toBe('cat-content-2');
});

test('活跃分类查询作用域', function () {
    // 创建活跃分类
    ContentCategory::create([
        'name' => 'active-cat-1',
        'display_name' => '活跃分类1',
        'is_active' => true,
    ]);

    ContentCategory::create([
        'name' => 'active-cat-2',
        'display_name' => '活跃分类2',
        'is_active' => true,
    ]);

    // 创建非活跃分类
    ContentCategory::create([
        'name' => 'inactive-cat',
        'display_name' => '非活跃分类',
        'is_active' => false,
    ]);

    // 测试活跃查询
    $activeCategories = ContentCategory::active()->get();
    expect($activeCategories)->toHaveCount(2);
    expect($activeCategories->pluck('name')->toArray())->toContain('active-cat-1', 'active-cat-2');
    expect($activeCategories->pluck('name')->toArray())->not->toContain('inactive-cat');
});

test('分类排序', function () {
    $parent = ContentCategory::create([
        'name' => 'sort-parent',
        'display_name' => '排序父级',
    ]);

    ContentCategory::create([
        'parent_id' => $parent->id,
        'name' => 'sort-cat-3',
        'display_name' => '分类3',
        'sort' => 30,
    ]);

    ContentCategory::create([
        'parent_id' => $parent->id,
        'name' => 'sort-cat-1',
        'display_name' => '分类1',
        'sort' => 10,
    ]);

    ContentCategory::create([
        'parent_id' => $parent->id,
        'name' => 'sort-cat-2',
        'display_name' => '分类2',
        'sort' => 20,
    ]);

    $sorted = ContentCategory::where('parent_id', $parent->id)->ordered()->get();
    expect($sorted->pluck('name')->toArray())->toBe(['sort-cat-1', 'sort-cat-2', 'sort-cat-3']);
});

test('分类的有效路径', function () {
    $level1 = ContentCategory::create([
        'name' => 'level1',
        'display_name' => '一级分类',
    ]);

    $level2 = ContentCategory::create([
        'parent_id' => $level1->id,
        'name' => 'level2',
        'display_name' => '二级分类',
    ]);

    $level3 = ContentCategory::create([
        'parent_id' => $level2->id,
        'name' => 'level3',
        'display_name' => '三级分类',
    ]);

    // 测试路径
    expect($level1->getPath())->toBe('level1');
    expect($level2->getPath())->toBe('level1/level2');
    expect($level3->getPath())->toBe('level1/level2/level3');

    // 测试面包屑
    $breadcrumbs = $level3->getBreadcrumbs();
    expect($breadcrumbs)->toHaveCount(3);
    expect($breadcrumbs->pluck('name')->toArray())->toBe(['level1', 'level2', 'level3']);
});

test('分类的层级深度', function () {
    $root = ContentCategory::create([
        'name' => 'root',
        'display_name' => '根分类',
    ]);

    $child = ContentCategory::create([
        'parent_id' => $root->id,
        'name' => 'child',
        'display_name' => '子分类',
    ]);

    $grandchild = ContentCategory::create([
        'parent_id' => $child->id,
        'name' => 'grandchild',
        'display_name' => '孙分类',
    ]);

    expect($root->getLevel())->toBe(0);
    expect($child->getLevel())->toBe(1);
    expect($grandchild->getLevel())->toBe(2);
});

test('获取所有后代分类', function () {
    $root = ContentCategory::create([
        'name' => 'descendants-root',
        'display_name' => '根分类',
    ]);

    $child1 = ContentCategory::create([
        'parent_id' => $root->id,
        'name' => 'desc-child1',
        'display_name' => '子分类1',
    ]);

    $child2 = ContentCategory::create([
        'parent_id' => $root->id,
        'name' => 'desc-child2',
        'display_name' => '子分类2',
    ]);

    $grandchild1 = ContentCategory::create([
        'parent_id' => $child1->id,
        'name' => 'desc-grandchild1',
        'display_name' => '孙分类1',
    ]);

    $grandchild2 = ContentCategory::create([
        'parent_id' => $child2->id,
        'name' => 'desc-grandchild2',
        'display_name' => '孙分类2',
    ]);

    $descendants = $root->getDescendants();
    expect($descendants)->toHaveCount(4);
    expect($descendants->pluck('name')->toArray())->toContain(
        'desc-child1', 'desc-child2', 'desc-grandchild1', 'desc-grandchild2'
    );
});

test('分类载荷数据', function () {
    $payload = [
        'seo' => [
            'meta_title' => 'SEO标题',
            'meta_description' => 'SEO描述',
        ],
        'settings' => [
            'show_in_menu' => true,
            'template' => 'grid',
        ],
    ];

    $category = ContentCategory::create([
        'name' => 'payload-category',
        'display_name' => '载荷分类',
        'payload' => $payload,
    ]);

    // 从数据库重新加载
    $loaded = ContentCategory::find($category->id);
    expect($loaded->payload)->toEqual($payload);
    expect($loaded->payload['seo']['meta_title'])->toBe('SEO标题');
});
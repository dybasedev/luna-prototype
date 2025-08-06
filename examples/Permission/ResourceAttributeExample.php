<?php

namespace Examples\Permission;

use Dybasedev\LunaPrototype\Permission\Attributes\Resource;

/**
 * 示例：使用 Resource 属性标记控制器
 */

// 基本用法 - 使用默认 CRUD 操作
#[Resource('user', '用户管理')]
class UserController
{
    public function index() {}
    public function create() {}
    public function store() {}
    public function show($id) {}
    public function edit($id) {}
    public function update($id) {}
    public function destroy($id) {}
}

// 自定义操作
#[Resource(
    name: 'article',
    description: '文章管理',
    actions: ['create', 'read', 'update', 'delete', 'publish', 'unpublish', 'archive'],
    group: 'content',
    sortOrder: 10
)]
class ArticleController
{
    public function index() {}
    public function create() {}
    public function store() {}
    public function show($id) {}
    public function edit($id) {}
    public function update($id) {}
    public function destroy($id) {}
    public function publish($id) {}
    public function unpublish($id) {}
    public function archive($id) {}
}

// 只读资源
#[Resource(
    name: 'report',
    description: '报表查看',
    actions: ['read', 'export'],
    group: 'analytics',
    sortOrder: 20,
    metadata: ['icon' => 'chart-bar']
)]
class ReportController
{
    public function index() {}
    public function show($id) {}
    public function export($id) {}
}

// 系统管理资源
#[Resource(
    name: 'system_settings',
    description: '系统设置',
    actions: ['read', 'update'],
    group: 'system',
    sortOrder: 100,
    visible: true,
    metadata: [
        'icon' => 'cog',
        'dangerous' => true,
        'requireSuperAdmin' => true
    ]
)]
class SystemSettingsController
{
    public function index() {}
    public function update() {}
}

// 使用静态工厂方法
#[Resource(...Resource::simple('category', '分类管理'))]
class CategoryController
{
    // 标准 CRUD 操作
}

#[Resource(...Resource::readOnly('log', '日志查看'))]
class LogController
{
    // 只读操作
}

#[Resource(...Resource::full('product', '产品管理'))]
class ProductController
{
    // 完整的 CRUD + 导入导出操作
}
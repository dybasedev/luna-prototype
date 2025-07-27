<?php

use Dybasedev\LunaPrototype\Showcase\RemoteSchema\RemoteSchema;
use Dybasedev\LunaPrototype\Showcase\RemoteSchema\RemoteSchemaRegistry;
use Dybasedev\LunaPrototype\Showcase\Attributes\RemoteSchemaMeta;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Illuminate\Http\Request;

// 测试用的基础 RemoteSchema 类
#[RemoteSchemaMeta(
    title: '测试表单',
    description: '用于测试的表单结构',
    group: 'test',
    sortOrder: 100
)]
class TestRemoteSchema extends RemoteSchema
{
    protected function title(): string
    {
        return '测试表单';
    }

    protected function description(): string
    {
        return '这是一个测试表单';
    }

    public function fields(Request $request): array
    {
        return [
            [
                'name' => 'title',
                'label' => '标题',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'content',
                'label' => '内容',
                'type' => 'textarea',
                'required' => false,
            ],
        ];
    }
}

// 测试用的 DataTable 风格 RemoteSchema 类
class TestDataTableRemoteSchema extends RemoteSchema
{
    protected function title(): string
    {
        return 'DataTable 表单';
    }

    public function fields(Request $request): array
    {
        $mode = $request->input('mode', 'create');
        
        if ($mode === 'create') {
            return [
                [
                    'name' => 'name',
                    'label' => '名称',
                    'type' => 'text',
                    'required' => true,
                ],
            ];
        }
        
        // edit mode
        return [
            [
                'name' => 'name',
                'label' => '名称',
                'type' => 'text',
                'required' => true,
                'disabled' => true,
            ],
            [
                'name' => 'status',
                'label' => '状态',
                'type' => 'select',
                'options' => [
                    ['value' => 'active', 'label' => '启用'],
                    ['value' => 'inactive', 'label' => '禁用'],
                ],
            ],
        ];
    }
}

// 测试用的配置风格 RemoteSchema 类
class TestConfigurationRemoteSchema extends RemoteSchema
{
    protected function title(): string
    {
        return '系统配置';
    }

    public function fields(Request $request): array
    {
        return [
            [
                'name' => 'site_name',
                'label' => '网站名称',
                'type' => 'text',
                'group' => 'general',
            ],
            [
                'name' => 'debug_mode',
                'label' => '调试模式',
                'type' => 'switch',
                'group' => 'advanced',
            ],
        ];
    }
    
    public function meta(Request $request): array
    {
        $meta = parent::meta($request);
        
        // 添加分组信息
        $meta['groups'] = [
            ['key' => 'general', 'title' => '常规设置'],
            ['key' => 'advanced', 'title' => '高级设置'],
        ];
        
        // 添加验证规则
        $meta['rules'] = [
            'site_name' => 'required|string|max:100',
            'debug_mode' => 'boolean',
        ];
        
        return $meta;
    }
}

test('可以注册和获取 RemoteSchema', function () {
    $registry = new RemoteSchemaRegistry();
    $registry->register('test_schema', TestRemoteSchema::class);
    
    expect($registry->has('test_schema'))->toBeTrue();
    
    $schema = $registry->get('test_schema');
    expect($schema)->toBeInstanceOf(TestRemoteSchema::class);
});

test('获取不存在的 RemoteSchema 会抛出异常', function () {
    $registry = new RemoteSchemaRegistry();
    
    $registry->get('non_existent');
})->throws(LunaException::class, "RemoteSchema 'non_existent' not found");

test('重复注册相同键会抛出异常', function () {
    $registry = new RemoteSchemaRegistry();
    $registry->register('test_schema', TestRemoteSchema::class);
    
    $registry->register('test_schema', TestRemoteSchema::class);
})->throws(LunaException::class, "RemoteSchema 'test_schema' already registered");

test('可以获取 RemoteSchema 的字段结构', function () {
    $schema = new TestRemoteSchema();
    $request = new Request();
    
    $fields = $schema->fields($request);
    
    expect($fields)->toHaveCount(2);
    expect($fields[0])->toMatchArray([
        'name' => 'title',
        'label' => '标题',
        'type' => 'text',
        'required' => true,
    ]);
});

test('可以获取 RemoteSchema 的元数据', function () {
    $schema = new TestRemoteSchema();
    $request = new Request();
    
    $meta = $schema->meta($request);
    
    expect($meta)->toMatchArray([
        'title' => '测试表单',
        'description' => '这是一个测试表单',
    ]);
});

test('RemoteSchema 可以支持多种模式', function () {
    $schema = new TestDataTableRemoteSchema();
    
    // 测试创建模式
    $createRequest = new Request(['mode' => 'create']);
    $createFields = $schema->fields($createRequest);
    
    expect($createFields)->toHaveCount(1);
    expect($createFields[0]['disabled'] ?? false)->toBeFalse();
    
    // 测试编辑模式
    $editRequest = new Request(['mode' => 'edit']);
    $editFields = $schema->fields($editRequest);
    
    expect($editFields)->toHaveCount(2);
    expect($editFields[0]['disabled'])->toBeTrue();
});

test('RemoteSchema 可以在 meta 中返回额外信息', function () {
    $schema = new TestConfigurationRemoteSchema();
    $request = new Request();
    
    $meta = $schema->meta($request);
    
    expect($meta)->toHaveKey('groups');
    expect($meta['groups'])->toHaveCount(2);
    expect($meta['groups'][0])->toMatchArray([
        'key' => 'general',
        'title' => '常规设置',
    ]);
    
    expect($meta)->toHaveKey('rules');
    expect($meta['rules']['site_name'])->toBe('required|string|max:100');
});

test('可以批量注册 RemoteSchema', function () {
    $registry = new RemoteSchemaRegistry();
    
    $registry->register('schema1', TestRemoteSchema::class);
    $registry->register('schema2', TestDataTableRemoteSchema::class);
    $registry->register('schema3', TestConfigurationRemoteSchema::class);
    
    expect($registry->keys())->toContain('schema1', 'schema2', 'schema3');
});

test('可以获取所有 RemoteSchema 的元数据', function () {
    $registry = new RemoteSchemaRegistry();
    
    $registry->register('test_schema', TestRemoteSchema::class, [
        'title' => '自定义标题',
        'group' => 'custom',
        'sortOrder' => 50,
    ]);
    
    $all = $registry->all();
    
    expect($all)->toHaveKey('test_schema');
    expect($all['test_schema'])->toMatchArray([
        'key' => 'test_schema',
        'title' => '自定义标题',
        'group' => 'custom',
        'sortOrder' => 50,
    ]);
});

test('可以按分组过滤 RemoteSchema', function () {
    $registry = new RemoteSchemaRegistry();
    
    $registry->register('schema1', TestRemoteSchema::class, ['group' => 'group1']);
    $registry->register('schema2', TestRemoteSchema::class, ['group' => 'group2']);
    $registry->register('schema3', TestRemoteSchema::class, ['group' => 'group1']);
    
    $group1Schemas = $registry->all('group1');
    
    expect($group1Schemas)->toHaveCount(2);
    expect(array_keys($group1Schemas))->toContain('schema1', 'schema3');
});

test('可以读取PHP 8属性元数据', function () {
    $registry = new RemoteSchemaRegistry();
    $registry->register('test_schema', TestRemoteSchema::class);
    
    $all = $registry->all();
    
    expect($all['test_schema'])->toMatchArray([
        'title' => '测试表单',
        'description' => '用于测试的表单结构',
        'group' => 'test',
        'sortOrder' => 100,
        'visible' => true,
    ]);
});

test('支持工厂函数注册', function () {
    $registry = new RemoteSchemaRegistry();
    
    $factory = function () {
        return new TestRemoteSchema();
    };
    
    $registry->register('factory_schema', $factory);
    
    $schema = $registry->get('factory_schema');
    expect($schema)->toBeInstanceOf(TestRemoteSchema::class);
});

test('不可见的 RemoteSchema 不会出现在列表中', function () {
    $registry = new RemoteSchemaRegistry();
    
    $registry->register('visible', TestRemoteSchema::class, ['visible' => true]);
    $registry->register('invisible', TestRemoteSchema::class, ['visible' => false]);
    
    $all = $registry->all();
    
    expect($all)->toHaveKey('visible');
    expect($all)->not->toHaveKey('invisible');
});

test('RemoteSchema 按 sortOrder 排序', function () {
    $registry = new RemoteSchemaRegistry();
    
    $registry->register('schema3', TestRemoteSchema::class, ['sortOrder' => 30]);
    $registry->register('schema1', TestRemoteSchema::class, ['sortOrder' => 10]);
    $registry->register('schema2', TestRemoteSchema::class, ['sortOrder' => 20]);
    
    $all = $registry->all();
    $keys = array_keys($all);
    
    expect($keys)->toBe(['schema1', 'schema2', 'schema3']);
});
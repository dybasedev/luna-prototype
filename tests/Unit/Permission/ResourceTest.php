<?php

namespace Tests\Unit\Permission;

use Dybasedev\LunaPrototype\Permission\Resources\ResourceDefinition;
use Dybasedev\LunaPrototype\Permission\Resources\SimpleResource;
use Dybasedev\LunaPrototype\Permission\Resources\ModelResource;
use Dybasedev\LunaPrototype\Permission\Resources\CallableResource;
use Dybasedev\LunaPrototype\Permission\Resources\ResourceRegistry;
use Illuminate\Database\Eloquent\Model;
use Dybasedev\LunaPrototype\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ResourceTest extends TestCase
{
    use RefreshDatabase;
    /** @test */
    public function it_creates_resource_definition()
    {
        $resource = new SimpleResource('posts', 'Blog posts');
        
        $this->assertEquals('posts', $resource->name);
        $this->assertEquals('Blog posts', $resource->description);
        $this->assertEquals([], $resource->actions);
    }

    /** @test */
    public function it_can_set_actions()
    {
        $resource = new SimpleResource('posts');
        $resource->setActions(['create', 'read', 'update', 'delete']);
        
        $this->assertEquals(['create', 'read', 'update', 'delete'], $resource->actions);
    }

    /** @test */
    public function it_can_add_actions()
    {
        $resource = new SimpleResource('posts');
        $resource->setActions(['read', 'write']);
        $resource->addActions('delete', 'admin');
        
        $this->assertEquals(['read', 'write', 'delete', 'admin'], $resource->actions);
    }

    /** @test */
    public function it_removes_duplicate_actions()
    {
        $resource = new SimpleResource('posts');
        $resource->setActions(['read', 'write']);
        $resource->addActions('read', 'delete', 'write');
        
        $this->assertEquals(['read', 'write', 'delete'], $resource->actions);
    }

    /** @test */
    public function it_checks_if_has_action()
    {
        $resource = new SimpleResource('posts');
        $resource->setActions(['read', 'write', 'delete']);
        
        $this->assertTrue($resource->hasAction('read'));
        $this->assertTrue($resource->hasAction('write'));
        $this->assertFalse($resource->hasAction('admin'));
        
        // 通配符支持
        $resource->setActions(['*']);
        $this->assertTrue($resource->hasAction('anything'));
    }

    /** @test */
    public function it_generates_resource_identifier()
    {
        $resource = new SimpleResource('posts');
        
        $this->assertEquals('posts', $resource->getIdentifier());
        $this->assertEquals('posts:123', $resource->getIdentifier('123'));
        $this->assertEquals('posts:123:read', $resource->getIdentifier('123', 'read'));
    }

    /** @test */
    public function it_creates_crud_resource()
    {
        $resource = SimpleResource::crud('articles', 'News articles');
        
        $this->assertEquals('articles', $resource->name);
        $this->assertEquals('News articles', $resource->description);
        $this->assertEquals(['create', 'read', 'update', 'delete', 'list'], $resource->actions);
    }

    /** @test */
    public function it_creates_read_only_resource()
    {
        $resource = SimpleResource::readOnly('logs', 'System logs');
        
        $this->assertEquals('logs', $resource->name);
        $this->assertEquals(['read', 'list'], $resource->actions);
    }

    /** @test */
    public function it_creates_admin_resource()
    {
        $resource = SimpleResource::admin('system', 'System configuration');
        
        $this->assertEquals('system', $resource->name);
        $this->assertEquals(['*'], $resource->actions);
        $this->assertTrue($resource->hasAction('any-action'));
    }

    /** @test */
    public function it_creates_from_array()
    {
        $definition = [
            'description' => 'User profiles',
            'actions' => ['read', 'update'],
        ];
        
        $resource = ResourceDefinition::fromArray('profiles', $definition);
        
        $this->assertInstanceOf(SimpleResource::class, $resource);
        $this->assertEquals('profiles', $resource->name);
        $this->assertEquals('User profiles', $resource->description);
        $this->assertEquals(['read', 'update'], $resource->actions);
    }

    /** @test */
    public function it_creates_model_resource()
    {
        $resource = new ModelResource(TestModel::class);
        
        $this->assertEquals('test_models', $resource->name);
        $this->assertEquals('Resource for ' . TestModel::class, $resource->description);
        $this->assertEquals(['create', 'read', 'update', 'delete', 'list'], $resource->actions);
        $this->assertEquals(TestModel::class, $resource->modelClass);
    }

    /** @test */
    public function it_generates_model_identifier()
    {
        $resource = new ModelResource(TestModel::class);
        $model = new TestModel(['id' => 'abc123']);
        
        $this->assertEquals('test_models:abc123', $resource->getModelIdentifier($model));
        $this->assertEquals('test_models:abc123:read', $resource->getModelIdentifier($model, 'read'));
    }

    /** @test */
    public function it_creates_read_only_model_resource()
    {
        $resource = ModelResource::readOnlyModel(TestModel::class);
        
        $this->assertEquals(['read', 'list'], $resource->actions);
    }

    /** @test */
    public function it_validates_model_class()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must extend ' . Model::class);
        
        new ModelResource(\stdClass::class);
    }

    /** @test */
    public function it_creates_callable_resource()
    {
        $resolver = function($name) {
            return [
                'description' => 'Dynamic resource',
                'actions' => ['custom1', 'custom2'],
            ];
        };
        
        $resource = new CallableResource('dynamic', $resolver);
        
        $this->assertEquals('dynamic', $resource->name);
        $this->assertEquals('Dynamic resource', $resource->getDescription());
        $this->assertEquals(['custom1', 'custom2'], $resource->getActions());
        $this->assertTrue($resource->hasAction('custom1'));
    }

    /** @test */
    public function callable_resource_returns_resource_definition()
    {
        $resolver = function($name) {
            return SimpleResource::crud($name, 'Dynamic CRUD');
        };
        
        $resource = new CallableResource('items', $resolver);
        
        $this->assertEquals(['create', 'read', 'update', 'delete', 'list'], $resource->getActions());
    }

    /** @test */
    public function callable_resource_caches_resolution()
    {
        $callCount = 0;
        $resolver = function($name) use (&$callCount) {
            $callCount++;
            return ['description' => 'Test', 'actions' => ['read']];
        };
        
        $resource = new CallableResource('test', $resolver);
        
        // 多次调用应该只解析一次
        $resource->description;
        $resource->actions;
        $resource->hasAction('read');
        
        $this->assertEquals(1, $callCount);
    }

    /** @test */
    public function resource_registry_can_register_resources()
    {
        $registry = new ResourceRegistry();
        
        // 注册简单资源
        $registry->register('posts', ['description' => 'Posts', 'actions' => ['read']]);
        
        // 注册资源对象
        $resource = SimpleResource::crud('articles');
        $registry->register('articles', $resource);
        
        // 注册可调用资源
        $registry->register('dynamic', function($name) {
            return ['description' => 'Dynamic', 'actions' => ['custom']];
        });
        
        $this->assertTrue($registry->has('posts'));
        $this->assertTrue($registry->has('articles'));
        $this->assertTrue($registry->has('dynamic'));
    }

    /** @test */
    public function resource_registry_gets_resources()
    {
        $registry = new ResourceRegistry();
        $registry->register('test', ['description' => 'Test', 'actions' => ['read']]);
        
        $resource = $registry->get('test');
        $this->assertInstanceOf(ResourceDefinition::class, $resource);
        $this->assertEquals('test', $resource->name);
        
        $notFound = $registry->get('non-existent');
        $this->assertNull($notFound);
    }

    /** @test */
    public function resource_registry_can_register_many()
    {
        $registry = new ResourceRegistry();
        
        $registry->registerMany([
            'posts' => ['description' => 'Posts', 'actions' => ['read', 'write']],
            'users' => SimpleResource::crud('users'),
            'logs' => function() { return SimpleResource::readOnly('logs'); },
        ]);
        
        $this->assertTrue($registry->has('posts'));
        $this->assertTrue($registry->has('users'));
        $this->assertTrue($registry->has('logs'));
    }

    /** @test */
    public function resource_registry_lists_all_resources()
    {
        $registry = new ResourceRegistry();
        $registry->registerMany([
            'resource1' => ['actions' => ['read']],
            'resource2' => ['actions' => ['write']],
            'resource3' => ['actions' => ['delete']],
        ]);
        
        $all = $registry->all();
        
        $this->assertCount(3, $all);
        $this->assertArrayHasKey('resource1', $all);
        $this->assertArrayHasKey('resource2', $all);
        $this->assertArrayHasKey('resource3', $all);
    }
}

/**
 * 测试模型
 */
class TestModel extends Model
{
    protected $fillable = ['id'];
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    
    /**
     * 获取属性
     */
    public function getAttribute($key)
    {
        return $this->attributes[$key] ?? null;
    }
}
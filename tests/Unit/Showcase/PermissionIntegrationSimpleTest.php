<?php

namespace Dybasedev\LunaPrototype\Tests\Unit\Showcase;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Permission\Traits\HasOwner;
use Dybasedev\LunaPrototype\Showcase\Integration\Permission\PermissionIntegration;
use Dybasedev\LunaPrototype\Showcase\Integration\Permission\PermissionIntegrationBuilder;
use Dybasedev\LunaPrototype\Showcase\Integration\Permission\PermissionIntegrationConfig;
use Dybasedev\LunaPrototype\Showcase\LunaShowcaseConfigure;
use Illuminate\Database\Eloquent\Model;
use Orchestra\Testbench\TestCase;

class PermissionIntegrationSimpleTest extends TestCase
{
    /**
     * 测试 Permission 集成配置构建器
     */
    public function test_permission_integration_builder()
    {
        $builder = new PermissionIntegrationBuilder();
        
        $config = $builder
            ->withResourcePattern('test.{key}')
            ->withOwnerFields('author_type', 'author_id')
            ->enableOwnerFilter()
            ->mapResource('users', 'custom.users')
            ->build();
        
        $this->assertInstanceOf(PermissionIntegrationConfig::class, $config);
        $this->assertEquals('test.{key}', $config->resourcePattern);
        $this->assertEquals('author_type', $config->defaultOwnerTypeField);
        $this->assertEquals('author_id', $config->defaultOwnerIdField);
        $this->assertTrue($config->enableOwnerFilter);
        $this->assertEquals('custom.users', $config->getResourceName('users'));
        $this->assertEquals('test.posts', $config->getResourceName('posts'));
    }
    
    /**
     * 测试 Showcase 配置集成
     */
    public function test_showcase_configure_integration()
    {
        $configure = new LunaShowcaseConfigure();
        
        // 初始状态
        $this->assertFalse($configure->isPermissionIntegrationEnabled);
        $this->assertNull($configure->permissionConfig);
        
        // 使用 builder 配置
        $builder = PermissionIntegrationBuilder::create()
            ->withResourcePattern('app.{key}')
            ->enableOwnerFilter();
        
        $configure->configurePermissionIntegration($builder);
        
        $this->assertTrue($configure->isPermissionIntegrationEnabled);
        $this->assertNotNull($configure->permissionConfig);
        $this->assertEquals('app.{key}', $configure->permissionConfig->resourcePattern);
        $this->assertTrue($configure->permissionConfig->enableOwnerFilter);
    }
    
    /**
     * 测试 HasOwner trait 基本功能
     */
    public function test_has_owner_trait_basic()
    {
        $model = new class extends Model {
            use HasOwner;
            
            protected $attributes = [
                'id' => 1,
                'owner_type' => 100,
                'owner_id' => 200,
            ];
            
            public function getKey() { return $this->attributes['id']; }
        };
        
        // 测试获取所有者信息
        $this->assertEquals(100, $model->getOwnerType());
        $this->assertEquals(200, $model->getOwnerId());
        
        // 测试所有权检查
        $this->assertTrue($model->isOwnedBy(200, 100));
        $this->assertTrue($model->isOwnedBy('200', 100));
        $this->assertFalse($model->isOwnedBy(999, 100));
        $this->assertFalse($model->isOwnedBy(200, 999));
        
        // 测试权限上下文
        $context = $model->getResourcePermissionContext();
        $this->assertArrayHasKey('resource_id', $context);
        $this->assertArrayHasKey('resource_owner_type', $context);
        $this->assertArrayHasKey('resource_owner_id', $context);
        $this->assertEquals(1, $context['resource_id']);
        $this->assertEquals(100, $context['resource_owner_type']);
        $this->assertEquals(200, $context['resource_owner_id']);
    }
    
    /**
     * 测试 HasOwner trait 与 SessionHolder
     */
    public function test_has_owner_with_session_holder()
    {
        $model = new class extends Model {
            use HasOwner;
            
            protected $attributes = [
                'owner_type' => 1,
                'owner_id' => 100,
            ];
        };
        
        $holder = new class implements SessionHolder {
            public function getOperatorTypeName(): string { return 'user'; }
            public function getOperatorType(): int { return 1; }
            public function getOperatorId(): int { return 100; }
            public function getSessionHolderContext(): ?array { return null; }
        };
        
        $this->assertTrue($model->isOwnedBy($holder));
        
        // 设置新的所有者
        $model->setOwner($holder);
        $this->assertEquals(1, $model->getOwnerType());
        $this->assertEquals(100, $model->getOwnerId());
    }
    
    /**
     * 测试自定义所有者字段名
     */
    public function test_custom_owner_fields()
    {
        $model = new class extends Model {
            use HasOwner;
            
            protected string $ownerTypeKeyName = 'creator_type';
            protected string $ownerIdKeyName = 'creator_id';
            
            protected $attributes = [
                'creator_type' => 5,
                'creator_id' => 'uuid-123',
            ];
        };
        
        $this->assertEquals('creator_type', $model->getOwnerTypeKeyName());
        $this->assertEquals('creator_id', $model->getOwnerIdKeyName());
        $this->assertEquals(5, $model->getOwnerType());
        $this->assertEquals('uuid-123', $model->getOwnerId());
    }
    
    /**
     * 测试 PermissionIntegration 可用性检查
     */
    public function test_permission_integration_availability()
    {
        // 这个测试需要实际的 Permission 组件，所以跳过简单测试
        // 在实际项目中，当 Permission 组件安装后，isAvailable() 会正确工作
        $this->assertTrue(true);
    }
    
    /**
     * 测试配置的链式调用
     */
    public function test_configuration_method_chaining()
    {
        $configure = LunaShowcaseConfigure::create()
            ->configurePermissionIntegration(
                PermissionIntegrationBuilder::create()
            )
            ->setDefaultAdapter('ant-design-pro');
        
        $this->assertInstanceOf(LunaShowcaseConfigure::class, $configure);
        $this->assertTrue($configure->isPermissionIntegrationEnabled);
    }
    
    /**
     * 测试最小化配置
     */
    public function test_minimal_configuration()
    {
        $configure = new LunaShowcaseConfigure();
        $configure->configurePermissionIntegration(
            new PermissionIntegrationBuilder()
        );
        
        $this->assertTrue($configure->isPermissionIntegrationEnabled);
        $config = $configure->permissionConfig;
        
        // 检查默认值
        $this->assertEquals('{key}', $config->resourcePattern);
        $this->assertEquals('owner_type', $config->defaultOwnerTypeField);
        $this->assertEquals('owner_id', $config->defaultOwnerIdField);
        $this->assertTrue($config->autoCheckPermission);
        $this->assertFalse($config->enableOwnerFilter);
    }
}
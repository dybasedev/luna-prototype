<?php

namespace Dybasedev\LunaPrototype\Tests\Unit\Showcase;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Permission\Traits\HasOwner;
use Dybasedev\LunaPrototype\Showcase\Integration\Permission\PermissionIntegrationBuilder;
use Dybasedev\LunaPrototype\Showcase\Integration\Permission\PermissionIntegrationConfig;
use Dybasedev\LunaPrototype\Showcase\LunaShowcaseConfigure;
use Illuminate\Database\Eloquent\Model;
use Orchestra\Testbench\TestCase;

class PermissionIntegrationTest extends TestCase
{
    public function test_permission_integration_builder()
    {
        $builder = new PermissionIntegrationBuilder();
        
        $config = $builder
            ->enable()
            ->withResourcePattern('admin.{key}')
            ->withOwnerFields('author_type', 'author_id')
            ->enableOwnerFilter()
            ->mapResource('users', 'users')
            ->mapResource('posts', 'content.posts')
            ->build();
        
        $this->assertInstanceOf(PermissionIntegrationConfig::class, $config);
        $this->assertTrue($config->enabled);
        $this->assertEquals('admin.{key}', $config->resourcePattern);
        $this->assertEquals('author_type', $config->defaultOwnerTypeField);
        $this->assertEquals('author_id', $config->defaultOwnerIdField);
        $this->assertTrue($config->enableOwnerFilter);
        $this->assertEquals('users', $config->getResourceName('users'));
        $this->assertEquals('content.posts', $config->getResourceName('posts'));
        $this->assertEquals('admin.orders', $config->getResourceName('orders'));
    }
    
    public function test_showcase_configure_with_permission_integration()
    {
        $configure = new LunaShowcaseConfigure();
        
        $this->assertFalse($configure->isPermissionIntegrationEnabled);
        $this->assertNull($configure->permissionConfig);
        
        // Test with closure configuration
        $configure->configurePermissionIntegration(function ($builder) {
            $builder->enable()
                ->withResourcePattern('test.{key}');
        });
        
        $this->assertTrue($configure->isPermissionIntegrationEnabled);
        $this->assertNotNull($configure->permissionConfig);
        $this->assertEquals('test.{key}', $configure->permissionConfig->resourcePattern);
    }
    
    public function test_has_owner_trait()
    {
        $model = new class extends Model implements SessionHolder {
            use HasOwner;
            
            protected $attributes = [
                'owner_type' => 123,
                'owner_id' => 456,
                'id' => 1
            ];
            
            public function getOperatorTypeName(): string
            {
                return 'test';
            }
            
            public function getOperatorType(): int
            {
                return 123;
            }
            
            public function getOperatorId(): int
            {
                return 456;
            }
            
            public function getSessionHolderContext(): ?array
            {
                return null;
            }
            
            public function getKey()
            {
                return $this->attributes['id'];
            }
        };
        
        $this->assertEquals(123, $model->getOwnerType());
        $this->assertEquals(456, $model->getOwnerId());
        
        // Test isOwnedBy with SessionHolder
        $holder = new class implements SessionHolder {
            public function getOperatorTypeName(): string { return 'test'; }
            public function getOperatorType(): int { return 123; }
            public function getOperatorId(): int { return 456; }
            public function getSessionHolderContext(): ?array { return null; }
        };
        
        $this->assertTrue($model->isOwnedBy($holder));
        
        // Test isOwnedBy with ID and type
        $this->assertTrue($model->isOwnedBy(456, 123));
        $this->assertFalse($model->isOwnedBy(999, 123));
        $this->assertFalse($model->isOwnedBy(456, 999));
        
        // Test setOwner
        $model->setOwner($holder);
        $this->assertEquals(123, $model->getOwnerType());
        $this->assertEquals(456, $model->getOwnerId());
        
        // Test getResourcePermissionContext
        $context = $model->getResourcePermissionContext();
        $this->assertArrayHasKey('resource_owner_id', $context);
        $this->assertArrayHasKey('resource_owner_type', $context);
        $this->assertArrayHasKey('resource_id', $context);
        $this->assertEquals(456, $context['resource_owner_id']);
        $this->assertEquals(123, $context['resource_owner_type']);
        $this->assertEquals(1, $context['resource_id']);
    }
    
    public function test_has_owner_trait_with_custom_fields()
    {
        $model = new class extends Model {
            use HasOwner;
            
            protected string $ownerTypeKeyName = 'author_type';
            protected string $ownerIdKeyName = 'author_id';
            
            protected $attributes = [
                'author_type' => 789,
                'author_id' => 'custom-id'
            ];
        };
        
        $this->assertEquals('author_type', $model->getOwnerTypeKeyName());
        $this->assertEquals('author_id', $model->getOwnerIdKeyName());
        $this->assertEquals(789, $model->getOwnerType());
        $this->assertEquals('custom-id', $model->getOwnerId());
    }
}
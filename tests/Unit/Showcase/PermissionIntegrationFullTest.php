<?php

namespace Dybasedev\LunaPrototype\Tests\Unit\Showcase;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Showcase\Integration\Permission\PermissionIntegration;
use Dybasedev\LunaPrototype\Showcase\Integration\Permission\PermissionIntegrationBuilder;
use Dybasedev\LunaPrototype\Showcase\Integration\Permission\PermissionIntegrationConfig;
use Dybasedev\LunaPrototype\Showcase\LunaShowcaseConfigure;
use Dybasedev\LunaPrototype\Showcase\UI;
use Illuminate\Database\Eloquent\Builder;
use Orchestra\Testbench\TestCase;
use Mockery;

class PermissionIntegrationFullTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
    
    public function test_permission_integration_is_available()
    {
        // 当 Permission 组件不存在时
        $this->app->instance('luna.permission', null);
        $this->assertFalse(PermissionIntegration::isAvailable());
        
        // 当 Permission 组件存在时
        $mockPermission = Mockery::mock(\Dybasedev\LunaPrototype\Permission\LunaPermission::class);
        $this->app->instance('luna.permission', $mockPermission);
        $this->assertTrue(PermissionIntegration::isAvailable());
    }
    
    public function test_get_current_holder()
    {
        // Test when no permission system is available
        $this->app->instance('luna.permission', null);
        $result = PermissionIntegration::getCurrentHolder();
        $this->assertNull($result);
        
        // Test when permission system is available but no operator bindings exist
        $mockPermission = Mockery::mock(\Dybasedev\LunaPrototype\Permission\LunaPermission::class);
        $this->app->instance('luna.permission', $mockPermission);
        
        $result = PermissionIntegration::getCurrentHolder();
        $this->assertNull($result); // Should return null when bindings are not properly set up
    }
    
    public function test_check_access_without_permission()
    {
        $this->app->instance('luna.permission', null);
        
        // Permission 不可用时默认返回 true（向后兼容）
        $result = PermissionIntegration::checkAccess('test.resource', 'read');
        $this->assertTrue($result);
    }
    
    public function test_check_access_with_permission()
    {
        $mockPermission = Mockery::mock(\Dybasedev\LunaPrototype\Permission\LunaPermission::class);
        $mockPermission->shouldReceive('can')
            ->with('read', 'test.resource')
            ->andReturn(true);
        $mockPermission->shouldReceive('can')
            ->with('write', 'test.resource')
            ->andReturn(false);
        
        $this->app->instance('luna.permission', $mockPermission);
        
        $this->assertTrue(PermissionIntegration::checkAccess('test.resource', 'read'));
        $this->assertFalse(PermissionIntegration::checkAccess('test.resource', 'write'));
    }
    
    public function test_apply_owner_filter()
    {
        // Mock Builder
        $builder = Mockery::mock(Builder::class);
        
        // Test without holder
        $this->app->instance('luna.permission', null);
        $config = new PermissionIntegrationConfig();
        
        $result = PermissionIntegration::applyOwnerFilter($builder, 'test.resource', $config);
        $this->assertSame($builder, $result);
        
        // Test with permission system available but no current holder (bindings not set up)
        $mockPermission = Mockery::mock(\Dybasedev\LunaPrototype\Permission\LunaPermission::class);
        $mockPermission->shouldReceive('can')
            ->with('view_all', 'test.resource')
            ->andReturn(false); // User doesn't have view_all permission
        
        $this->app->instance('luna.permission', $mockPermission);
        
        // When getCurrentHolder() returns null (no bindings), it should apply whereRaw('1 = 0') to return empty results
        $builder->shouldReceive('whereRaw')->with('1 = 0')->andReturnSelf();
        
        $result = PermissionIntegration::applyOwnerFilter($builder, 'test.resource', $config);
        $this->assertSame($builder, $result);
    }
    
    public function test_filter_visible_columns()
    {
        $columns = [
            UI::column('id')->title('ID'),
            UI::column('name')->title('Name'),
            UI::column('email')->title('Email'),
            UI::column('phone')->title('Phone'),
            UI::column('balance')->title('Balance'),
        ];
        
        $columnPermissions = [
            'email' => 'view_email',
            'phone' => [
                'action' => 'view_phone',
                'resource' => 'test.phones'
            ],
            'balance' => 'view_balance',
        ];
        
        // Mock Permission
        $mockPermission = Mockery::mock(\Dybasedev\LunaPrototype\Permission\LunaPermission::class);
        $mockPermission->shouldReceive('can')
            ->with('view_email', 'test.resource')
            ->andReturn(true);
        $mockPermission->shouldReceive('can')
            ->with('view_phone', 'test.phones')
            ->andReturn(false);
        $mockPermission->shouldReceive('can')
            ->with('view_balance', 'test.resource')
            ->andReturn(true);
        
        $this->app->instance('luna.permission', $mockPermission);
        
        $filtered = PermissionIntegration::filterVisibleColumns($columns, 'test.resource', $columnPermissions);
        
        // 应该包含 id, name, email, balance，但不包含 phone
        $this->assertCount(4, $filtered);
        $names = array_map(fn($col) => $col->name, $filtered);
        $this->assertContains('id', $names);
        $this->assertContains('name', $names);
        $this->assertContains('email', $names);
        $this->assertContains('balance', $names);
        $this->assertNotContains('phone', $names);
    }
    
    public function test_filter_visible_columns_without_permission()
    {
        $this->app->instance('luna.permission', null);
        
        $columns = [
            UI::column('id')->title('ID'),
            UI::column('email')->title('Email'),
        ];
        
        $columnPermissions = [
            'email' => 'view_email',
        ];
        
        // Permission 不可用时返回所有列
        $filtered = PermissionIntegration::filterVisibleColumns($columns, 'test.resource', $columnPermissions);
        $this->assertCount(2, $filtered);
    }
    
    public function test_showcase_helper_functions()
    {
        // Mock Permission
        $mockPermission = Mockery::mock(\Dybasedev\LunaPrototype\Permission\LunaPermission::class);
        $mockPermission->shouldReceive('can')
            ->with('read', 'test.users')
            ->andReturn(true);
        
        $this->app->instance('luna.permission', $mockPermission);
        
        // Test luna_showcase_check_permission
        $result = luna_showcase_check_permission('users', 'read');
        $this->assertTrue($result);
    }
    
    public function test_permission_integration_config_methods()
    {
        $config = new PermissionIntegrationConfig();
        
        // Test default values
        $this->assertEquals('{key}', $config->resourcePattern);
        $this->assertEquals('owner_type', $config->defaultOwnerTypeField);
        $this->assertEquals('owner_id', $config->defaultOwnerIdField);
        $this->assertTrue($config->autoCheckPermission);
        $this->assertFalse($config->enableOwnerFilter);
        $this->assertEmpty($config->resourceMappings);
        
        // Test getResourceName with pattern
        $config->resourcePattern = 'admin.{key}';
        $this->assertEquals('admin.users', $config->getResourceName('users'));
        
        // Test getResourceName with mapping
        $config->resourceMappings = ['users' => 'system.users'];
        $this->assertEquals('system.users', $config->getResourceName('users'));
        $this->assertEquals('admin.posts', $config->getResourceName('posts'));
    }
    
    public function test_permission_integration_builder_methods()
    {
        $builder = new PermissionIntegrationBuilder();
        
        // Test all builder methods
        $config = $builder
            ->withResourcePattern('test.{key}')
            ->withOwnerFields('creator_type', 'creator_id')
            ->enableOwnerFilter()
            ->disableAutoCheck()
            ->mapResource('users', 'custom.users')
            ->mapResource('posts', 'custom.posts')
            ->build();
        
        $this->assertEquals('test.{key}', $config->resourcePattern);
        $this->assertEquals('creator_type', $config->defaultOwnerTypeField);
        $this->assertEquals('creator_id', $config->defaultOwnerIdField);
        $this->assertTrue($config->enableOwnerFilter);
        $this->assertFalse($config->autoCheckPermission);
        $this->assertEquals([
            'users' => 'custom.users',
            'posts' => 'custom.posts',
        ], $config->resourceMappings);
    }
    
    public function test_showcase_configure_integration()
    {
        $configure = new LunaShowcaseConfigure();
        
        // Test default state
        $this->assertFalse($configure->isPermissionIntegrationEnabled);
        $this->assertNull($configure->permissionConfig);
        
        // Test with direct config
        $config = (new PermissionIntegrationBuilder())
            ->withResourcePattern('direct.{key}')
            ->build();
        
        $configure->withPermissionIntegration($config);
        
        $this->assertTrue($configure->isPermissionIntegrationEnabled);
        $this->assertNotNull($configure->permissionConfig);
        $this->assertEquals('direct.{key}', $configure->permissionConfig->resourcePattern);
        
        // Test with builder
        $config2 = (new PermissionIntegrationBuilder())
            ->withResourcePattern('app.{key}')
            ->enableOwnerFilter()
            ->build();
        
        $configure2 = new LunaShowcaseConfigure();
        $configure2->withPermissionIntegration($config);
        
        $this->assertTrue($configure2->isPermissionIntegrationEnabled);
        $this->assertEquals('direct.{key}', $configure2->permissionConfig->resourcePattern);
    }
}
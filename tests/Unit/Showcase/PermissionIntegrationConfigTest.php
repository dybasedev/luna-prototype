<?php

namespace Dybasedev\LunaPrototype\Tests\Unit\Showcase;

use Dybasedev\LunaPrototype\Showcase\LunaShowcaseConfigure;
use Dybasedev\LunaPrototype\Showcase\Integration\Permission\PermissionIntegrationBuilder;
use Dybasedev\LunaPrototype\Showcase\Integration\Permission\PermissionIntegrationConfig;
use Orchestra\Testbench\TestCase;

class PermissionIntegrationConfigTest extends TestCase
{
    public function test_configure_permission_integration_with_builder()
    {
        $configure = new LunaShowcaseConfigure();
        
        $builder = PermissionIntegrationBuilder::create()
            ->withResourcePattern('test.{key}')
            ->withOwnerFields('custom_type', 'custom_id')
            ->enableOwnerFilter()
            ->mapResource('users', 'custom.users');
        
        $configure->configurePermissionIntegration($builder);
        
        $this->assertTrue($configure->isPermissionIntegrationEnabled);
        $this->assertNotNull($configure->permissionConfig);
        $this->assertInstanceOf(PermissionIntegrationConfig::class, $configure->permissionConfig);
        $this->assertEquals('test.{key}', $configure->permissionConfig->resourcePattern);
        $this->assertEquals('custom_type', $configure->permissionConfig->defaultOwnerTypeField);
        $this->assertEquals('custom_id', $configure->permissionConfig->defaultOwnerIdField);
        $this->assertTrue($configure->permissionConfig->enableOwnerFilter);
        $this->assertEquals('custom.users', $configure->permissionConfig->getResourceName('users'));
    }
    
    public function test_configure_permission_integration_minimal()
    {
        $configure = new LunaShowcaseConfigure();
        
        $configure->configurePermissionIntegration(
            PermissionIntegrationBuilder::create()
        );
        
        $this->assertTrue($configure->isPermissionIntegrationEnabled);
        $this->assertNotNull($configure->permissionConfig);
        
        // Check default values
        $this->assertEquals('{key}', $configure->permissionConfig->resourcePattern);
        $this->assertEquals('owner_type', $configure->permissionConfig->defaultOwnerTypeField);
        $this->assertEquals('owner_id', $configure->permissionConfig->defaultOwnerIdField);
        $this->assertTrue($configure->permissionConfig->autoCheckPermission);
        $this->assertFalse($configure->permissionConfig->enableOwnerFilter);
    }
    
    public function test_with_permission_integration_direct_config()
    {
        $configure = new LunaShowcaseConfigure();
        
        $config = (new PermissionIntegrationBuilder())
            ->withResourcePattern('direct.{key}')
            ->build();
        
        $configure->withPermissionIntegration($config);
        
        $this->assertTrue($configure->isPermissionIntegrationEnabled);
        $this->assertNotNull($configure->permissionConfig);
        $this->assertEquals('direct.{key}', $configure->permissionConfig->resourcePattern);
    }
    
    public function test_method_chaining()
    {
        $configure = LunaShowcaseConfigure::create()
            ->configurePermissionIntegration(
                PermissionIntegrationBuilder::create()
            )
            ->setDefaultAdapter('ant-design-pro');
        
        $this->assertInstanceOf(LunaShowcaseConfigure::class, $configure);
        $this->assertTrue($configure->isPermissionIntegrationEnabled);
        
        // Test that configure can be called in different order
        $configure2 = LunaShowcaseConfigure::create()
            ->setDefaultAdapter('ant-design-pro')
            ->configurePermissionIntegration(
                PermissionIntegrationBuilder::create()
                    ->withResourcePattern('chained.{key}')
            );
        
        $this->assertTrue($configure2->isPermissionIntegrationEnabled);
        $this->assertEquals('chained.{key}', $configure2->permissionConfig->resourcePattern);
    }
    
    public function test_builder_static_factory()
    {
        $builder = PermissionIntegrationBuilder::create();
        $this->assertInstanceOf(PermissionIntegrationBuilder::class, $builder);
        
        $builder->withResourcePattern('factory.{key}');
        $config = $builder->build();
        
        $this->assertEquals('factory.{key}', $config->resourcePattern);
    }
}
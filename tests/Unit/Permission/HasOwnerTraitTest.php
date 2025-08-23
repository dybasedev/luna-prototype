<?php

namespace Dybasedev\LunaPrototype\Tests\Unit\Permission;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Permission\Traits\HasOwner;
use Illuminate\Database\Eloquent\Model;
use Orchestra\Testbench\TestCase;

class HasOwnerTraitTest extends TestCase
{
    public function test_default_owner_field_names()
    {
        $model = new class extends Model {
            use HasOwner;
        };
        
        $this->assertEquals('owner_type', $model->getOwnerTypeKeyName());
        $this->assertEquals('owner_id', $model->getOwnerIdKeyName());
    }
    
    public function test_custom_owner_field_names()
    {
        $model = new class extends Model {
            use HasOwner;
            
            protected string $ownerTypeKeyName = 'creator_type';
            protected string $ownerIdKeyName = 'creator_id';
        };
        
        $this->assertEquals('creator_type', $model->getOwnerTypeKeyName());
        $this->assertEquals('creator_id', $model->getOwnerIdKeyName());
    }
    
    public function test_get_owner_type_and_id()
    {
        $model = new class extends Model {
            use HasOwner;
            
            protected $attributes = [
                'owner_type' => 1,
                'owner_id' => 100,
            ];
        };
        
        $this->assertEquals(1, $model->getOwnerType());
        $this->assertEquals(100, $model->getOwnerId());
    }
    
    public function test_get_owner_with_custom_fields()
    {
        $model = new class extends Model {
            use HasOwner;
            
            protected string $ownerTypeKeyName = 'author_type';
            protected string $ownerIdKeyName = 'author_id';
            
            protected $attributes = [
                'author_type' => 2,
                'author_id' => 'custom-id-123',
            ];
        };
        
        $this->assertEquals(2, $model->getOwnerType());
        $this->assertEquals('custom-id-123', $model->getOwnerId());
    }
    
    public function test_is_owned_by_session_holder()
    {
        $model = new class extends Model {
            use HasOwner;
            
            protected $attributes = [
                'owner_type' => 1,
                'owner_id' => 100,
            ];
        };
        
        // Create matching SessionHolder
        $holder = new class implements SessionHolder {
            public function getOperatorTypeName(): string { return 'user'; }
            public function getOperatorType(): int { return 1; }
            public function getOperatorId(): int { return 100; }
            public function getSessionHolderContext(): ?array { return null; }
        };
        
        $this->assertTrue($model->isOwnedBy($holder));
        
        // Create non-matching SessionHolder
        $otherHolder = new class implements SessionHolder {
            public function getOperatorTypeName(): string { return 'admin'; }
            public function getOperatorType(): int { return 2; }
            public function getOperatorId(): int { return 200; }
            public function getSessionHolderContext(): ?array { return null; }
        };
        
        $this->assertFalse($model->isOwnedBy($otherHolder));
    }
    
    public function test_is_owned_by_id_and_type()
    {
        $model = new class extends Model {
            use HasOwner;
            
            protected $attributes = [
                'owner_type' => 1,
                'owner_id' => 100,
            ];
        };
        
        // Test with matching ID and type
        $this->assertTrue($model->isOwnedBy(100, 1));
        
        // Test with string ID
        $this->assertTrue($model->isOwnedBy('100', 1));
        
        // Test with wrong ID
        $this->assertFalse($model->isOwnedBy(200, 1));
        
        // Test with wrong type
        $this->assertFalse($model->isOwnedBy(100, 2));
        
        // Test without type (should match any type)
        $this->assertTrue($model->isOwnedBy(100));
        $this->assertFalse($model->isOwnedBy(200));
    }
    
    public function test_set_owner()
    {
        $model = new class extends Model {
            use HasOwner;
            
            protected $attributes = [];
        };
        
        $holder = new class implements SessionHolder {
            public function getOperatorTypeName(): string { return 'user'; }
            public function getOperatorType(): int { return 3; }
            public function getOperatorId(): int { return 300; }
            public function getSessionHolderContext(): ?array { return null; }
        };
        
        $result = $model->setOwner($holder);
        
        // Should return self for chaining
        $this->assertSame($model, $result);
        
        // Should set owner fields
        $this->assertEquals(3, $model->getOwnerType());
        $this->assertEquals(300, $model->getOwnerId());
    }
    
    public function test_get_resource_permission_context()
    {
        $model = new class extends Model {
            use HasOwner;
            
            protected $attributes = [
                'id' => 999,
                'owner_type' => 5,
                'owner_id' => 500,
            ];
            
            public function getKey()
            {
                return $this->attributes['id'];
            }
        };
        
        $context = $model->getResourcePermissionContext();
        
        $this->assertIsArray($context);
        $this->assertArrayHasKey('resource_id', $context);
        $this->assertArrayHasKey('resource_owner_type', $context);
        $this->assertArrayHasKey('resource_owner_id', $context);
        $this->assertEquals(999, $context['resource_id']);
        $this->assertEquals(5, $context['resource_owner_type']);
        $this->assertEquals(500, $context['resource_owner_id']);
    }
    
    public function test_get_resource_permission_context_with_additional_attributes()
    {
        $model = new class extends Model {
            use HasOwner;
            
            protected $attributes = [
                'id' => 1,
                'owner_type' => 1,
                'owner_id' => 100,
                'status' => 'active',
                'visibility' => 'public',
            ];
            
            public function getKey()
            {
                return $this->attributes['id'];
            }
            
            protected function getPermissionAttributes(): array
            {
                return [
                    'status' => $this->attributes['status'],
                    'visibility' => $this->attributes['visibility'],
                ];
            }
        };
        
        $context = $model->getResourcePermissionContext();
        
        $this->assertArrayHasKey('status', $context);
        $this->assertArrayHasKey('visibility', $context);
        $this->assertEquals('active', $context['status']);
        $this->assertEquals('public', $context['visibility']);
    }
    
    public function test_has_owner_trait_with_null_owner()
    {
        $model = new class extends Model {
            use HasOwner;
            
            protected $attributes = [
                'owner_type' => null,
                'owner_id' => null,
            ];
        };
        
        $this->assertNull($model->getOwnerType());
        $this->assertNull($model->getOwnerId());
        
        // Should not match any owner when owner is null
        $holder = new class implements SessionHolder {
            public function getOperatorTypeName(): string { return 'user'; }
            public function getOperatorType(): int { return 1; }
            public function getOperatorId(): int { return 100; }
            public function getSessionHolderContext(): ?array { return null; }
        };
        
        $this->assertFalse($model->isOwnedBy($holder));
        $this->assertFalse($model->isOwnedBy(100, 1));
    }
    
    public function test_owner_comparison_with_different_types()
    {
        // Test with integer owner_id
        $model1 = new class extends Model {
            use HasOwner;
            
            protected $attributes = [
                'owner_type' => 1,
                'owner_id' => 100,
            ];
        };
        
        // Test with string owner_id
        $model2 = new class extends Model {
            use HasOwner;
            
            protected $attributes = [
                'owner_type' => 2,
                'owner_id' => 'uuid-123-456',
            ];
        };
        
        $this->assertTrue($model1->isOwnedBy(100, 1));
        $this->assertTrue($model1->isOwnedBy('100', 1));
        
        $this->assertTrue($model2->isOwnedBy('uuid-123-456', 2));
        $this->assertFalse($model2->isOwnedBy('uuid-789', 2));
    }
}
<?php

use Dybasedev\LunaPrototype\Permission\Attributes\Resource;

describe('Resource Attribute', function () {
    
    it('can create a resource with default values', function () {
        $resource = new Resource('user');
        
        expect($resource->name)->toBe('user');
        expect($resource->description)->toBe('');
        expect($resource->actions)->toBe(['create', 'read', 'update', 'delete']);
        expect($resource->group)->toBe('default');
        expect($resource->sortOrder)->toBe(0);
        expect($resource->visible)->toBeTrue();
        expect($resource->metadata)->toBe([]);
    });
    
    it('can create a resource with custom values', function () {
        $resource = new Resource(
            name: 'article',
            description: '文章资源',
            actions: ['create', 'read', 'update', 'delete', 'publish'],
            group: 'content',
            sortOrder: 10,
            visible: false,
            metadata: ['icon' => 'document']
        );
        
        expect($resource->name)->toBe('article');
        expect($resource->description)->toBe('文章资源');
        expect($resource->actions)->toBe(['create', 'read', 'update', 'delete', 'publish']);
        expect($resource->group)->toBe('content');
        expect($resource->sortOrder)->toBe(10);
        expect($resource->visible)->toBeFalse();
        expect($resource->metadata)->toBe(['icon' => 'document']);
    });
    
    it('can check if action is supported', function () {
        $resource = new Resource('user', actions: ['create', 'read']);
        
        expect($resource->hasAction('create'))->toBeTrue();
        expect($resource->hasAction('read'))->toBeTrue();
        expect($resource->hasAction('update'))->toBeFalse();
        expect($resource->hasAction('delete'))->toBeFalse();
    });
    
    it('supports wildcard actions', function () {
        $resource = new Resource('admin', actions: ['*']);
        
        expect($resource->hasAction('create'))->toBeTrue();
        expect($resource->hasAction('read'))->toBeTrue();
        expect($resource->hasAction('anything'))->toBeTrue();
    });
    
    it('can get permission identifiers', function () {
        $resource = new Resource('user', actions: ['create', 'read', 'update']);
        
        expect($resource->getPermissionIdentifiers())->toBe([
            'user:create',
            'user:read',
            'user:update'
        ]);
    });
    
    it('can convert to array', function () {
        $resource = new Resource(
            name: 'product',
            description: '产品资源',
            actions: ['create', 'read'],
            group: 'catalog',
            sortOrder: 5
        );
        
        expect($resource->toArray())->toBe([
            'name' => 'product',
            'description' => '产品资源',
            'actions' => ['create', 'read'],
            'group' => 'catalog',
            'sortOrder' => 5,
            'visible' => true,
            'metadata' => []
        ]);
    });
    
    it('can create simple resource', function () {
        $resource = Resource::simple('order', '订单资源');
        
        expect($resource->name)->toBe('order');
        expect($resource->description)->toBe('订单资源');
        expect($resource->actions)->toBe(['create', 'read', 'update', 'delete']);
    });
    
    it('can create read-only resource', function () {
        $resource = Resource::readOnly('report', '报表资源');
        
        expect($resource->name)->toBe('report');
        expect($resource->description)->toBe('报表资源');
        expect($resource->actions)->toBe(['read']);
    });
    
    it('can create full permission resource', function () {
        $resource = Resource::full('inventory', '库存资源');
        
        expect($resource->name)->toBe('inventory');
        expect($resource->description)->toBe('库存资源');
        expect($resource->actions)->toBe(['create', 'read', 'update', 'delete', 'list', 'export', 'import']);
    });
    
    it('can be used as attribute on class', function () {
        $reflection = new ReflectionClass(ResourceTestClass::class);
        $attributes = $reflection->getAttributes(Resource::class);
        
        expect($attributes)->toHaveCount(1);
        
        $resource = $attributes[0]->newInstance();
        expect($resource->name)->toBe('test');
        expect($resource->description)->toBe('测试资源');
    });
});

#[Resource('test', '测试资源', ['read', 'write'])]
class ResourceTestClass
{
    // Test class for attribute usage
}
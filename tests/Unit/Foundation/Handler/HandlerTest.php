<?php

use Dybasedev\LunaPrototype\Foundation\Handler\BaseHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandlerConfigure;
use Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// 创建一个测试用的处理器
class TestHandler extends BaseHandler
{
    public function handlerName(): string
    {
        return 'test_handler';
    }
    
    public function handlerDescription(): string
    {
        return 'A test handler for unit testing';
    }
    
    public function handle(array $data): mixed
    {
        return 'handled: ' . ($data['message'] ?? 'no message');
    }
}

beforeEach(function () {
    $this->configure = LunaHandlerConfigure::create()
        ->addGroup('test_group', 'Test Group')
        ->addHandler(TestHandler::class)
        ->build();
    $this->handler = new LunaHandler($this->configure, app('cache.store'));
});

it('可以创建实体处理器', function () {
    $config = new \Dybasedev\LunaPrototype\Foundation\Configuration\Repository(['default_config' => 'value']);
    
    $created = $this->handler->createEntityHandler(
        'test_group',
        'test.handler',
        TestHandler::class,
        $config,
        'Test Handler',
        'A handler for testing'
    );
    
    expect($created)->toBeInstanceOf(Handler::class);
    expect($created->name)->toBe('test.handler');
    expect($created->handler)->toBe(TestHandler::class);
    expect($created->display_name)->toBe('Test Handler');
    expect($created->description)->toBe('A handler for testing');
    expect($created->config)->toBe(['default_config' => 'value']);
    expect($created->id)->toBe(hash_code('test.handler'));
    expect($created->enabled)->toBeTrue();
});

it('可以检查实体处理器是否存在', function () {
    $this->handler->createEntityHandler('test_group', 'existing.handler', TestHandler::class);
    
    expect($this->handler->existsEntityHandler('existing.handler'))->toBeTrue();
    expect($this->handler->existsEntityHandler(hash_code('existing.handler')))->toBeTrue();
    expect($this->handler->existsEntityHandler('non.existing'))->toBeFalse();
});

it('可以获取实体处理器', function () {
    $this->handler->createEntityHandler('test_group', 'get.handler', TestHandler::class);
    
    $handler = $this->handler->entityHandler('get.handler');
    
    expect($handler)->toBeInstanceOf(Handler::class);
    expect($handler->name)->toBe('get.handler');
});

it('对于不存在的实体处理器返回 null', function () {
    $handler = $this->handler->entityHandler('non.existing');
    
    expect($handler)->toBeNull();
});

it('可以按组获取实体处理器', function () {
    $this->handler->createEntityHandler('test_group', 'handler1', TestHandler::class);
    $this->handler->createEntityHandler('test_group', 'handler2', TestHandler::class);
    $this->handler->createEntityHandler('test_group', 'handler3', TestHandler::class);
    
    $handlers = $this->handler->entityHandlers('test_group');
    
    expect($handlers)->toBeArray();
    expect($handlers)->toHaveCount(3);
    expect(array_map(fn($h) => $h->name, $handlers))->toContain('handler1', 'handler2', 'handler3');
});

it('可以获取所有实体处理器', function () {
    $this->handler->createEntityHandler('test_group', 'handler1', TestHandler::class, null, 'Handler 1');
    $this->handler->createEntityHandler('test_group', 'handler2', TestHandler::class, null, 'Handler 2');
    $this->handler->createEntityHandler('test_group', 'handler3', TestHandler::class, null, 'Handler 3');
    
    $handlers = $this->handler->getAllEntityHandlers();
    
    expect($handlers)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($handlers)->toHaveCount(3);
    expect($handlers->pluck('name')->toArray())->toContain('handler1', 'handler2', 'handler3');
});

it('缓存实体处理器', function () {
    $cache = app('cache.store');
    $cache->forget('handler:entities');
    
    // 创建处理器
    $this->handler->createEntityHandler('test_group', 'cached.handler', TestHandler::class);
    
    // 第一次调用
    $handlers1 = $this->handler->getAllEntityHandlers();
    
    // 验证缓存存在
    expect($cache->has('handler:entities'))->toBeTrue();
    
    // 第二次调用应该从缓存获取
    $handlers2 = $this->handler->getAllEntityHandlers();
    
    expect($handlers1->count())->toBe($handlers2->count());
});

it('处理器可以处理数据', function () {
    $handler = new TestHandler();
    
    $result = $handler->handle(['message' => 'Hello World']);
    
    expect($result)->toBe('handled: Hello World');
});
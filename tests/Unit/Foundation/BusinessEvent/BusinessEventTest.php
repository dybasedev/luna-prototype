<?php

use Dybasedev\LunaPrototype\Foundation\BusinessEvent\LunaBusinessEvent;
use Dybasedev\LunaPrototype\Foundation\BusinessEvent\LunaBusinessEventConfigure;
use Dybasedev\LunaPrototype\Foundation\BusinessEvent\Models\BusinessEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->configure = LunaBusinessEventConfigure::create()
        ->addGroup('user', 'User Events')
        ->addGroup('order', 'Order Events')
        ->build();
    
    $handlerConfigure = \Dybasedev\LunaPrototype\Foundation\Handler\LunaHandlerConfigure::create()
        ->addGroup('business_event', 'Business Event Handlers')
        ->build();
    $handler = new \Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler($handlerConfigure, app('cache.store'));
    
    $this->businessEvent = new LunaBusinessEvent($this->configure, $handler, app('cache.store'));
});

it('can create a business event', function () {
    $event = $this->businessEvent->createBusinessEvent(
        'user.registered',
        'user',
        'default_handler',
        'User {user_name} registered at {time}',
        'User Registered'
    );
    
    expect($event)->toBeInstanceOf(BusinessEvent::class);
    expect($event->name)->toBe('user.registered');
    expect($event->display_name)->toBe('User Registered');
    expect($event->formatter)->toBe('User {user_name} registered at {time}');
    expect($event->id)->toBe(hash_code('user.registered'));
    expect($event->group_id)->toBe(hash_code('user'));
    expect($event->handler_id)->toBe(hash_code('default_handler'));
});

it('can create event with config', function () {
    $config = new \Dybasedev\LunaPrototype\Foundation\Configuration\Repository(['setting' => 'value']);
    
    $event = $this->businessEvent->createBusinessEvent(
        'order.created',
        'order',
        'order_handler',
        'Order #{order_id} created',
        'Order Created',
        $config
    );
    
    expect($event->config)->toBe(['setting' => 'value']);
});

it('can retrieve all business events', function () {
    // 创建几个事件
    $this->businessEvent->createBusinessEvent('event1', 'user', 'handler1', 'Event 1 occurred', 'Event 1');
    $this->businessEvent->createBusinessEvent('event2', 'user', 'handler2', 'Event 2 occurred', 'Event 2');
    $this->businessEvent->createBusinessEvent('event3', 'order', 'handler3', 'Event 3 occurred', 'Event 3');
    
    $events = $this->businessEvent->getAllEvents();
    
    expect($events)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($events)->toHaveCount(3);
    expect($events->pluck('name')->toArray())->toBe(['event1', 'event2', 'event3']);
});

it('can check if business event exists', function () {
    $this->businessEvent->createBusinessEvent('existing.event', 'user', 'handler', 'Event exists', 'Existing Event');
    
    expect($this->businessEvent->existsBusinessEvent('existing.event'))->toBeTrue();
    expect($this->businessEvent->existsBusinessEvent(hash_code('existing.event')))->toBeTrue();
    expect($this->businessEvent->existsBusinessEvent('non.existing'))->toBeFalse();
});

it('can get events by group', function () {
    $this->businessEvent->createBusinessEvent('user.login', 'user', 'handler', 'User logged in', 'User Login');
    $this->businessEvent->createBusinessEvent('user.logout', 'user', 'handler', 'User logged out', 'User Logout');
    $this->businessEvent->createBusinessEvent('order.created', 'order', 'handler', 'Order created', 'Order Created');
    
    $userEvents = $this->businessEvent->events('user');
    $orderEvents = $this->businessEvent->events('order');
    
    expect($userEvents)->toHaveCount(2);
    expect($orderEvents)->toHaveCount(1);
    expect(array_column($userEvents, 'name'))->toContain('user.login', 'user.logout');
    expect(array_column($orderEvents, 'name'))->toContain('order.created');
});

it('can get all groups', function () {
    $groups = $this->businessEvent->groups();
    
    expect($groups)->toBeArray();
    expect(count($groups))->toBeGreaterThan(0);
    
    $groupNames = array_column($groups, 'name');
    expect($groupNames)->toContain('user', 'order', 'common');
});

it('caches business events', function () {
    $cache = app('cache.store');
    
    // 清除缓存
    $cache->forget('business-event:events');
    
    // 第一次调用应该从数据库加载
    $this->businessEvent->createBusinessEvent('cached.event', 'user', 'handler', 'This is cached', 'Cached Event');
    $events1 = $this->businessEvent->getAllEvents();
    
    // 第二次调用应该从缓存加载
    $events2 = $this->businessEvent->getAllEvents();
    
    expect($events1->count())->toBe($events2->count());
    expect($cache->has('business-event:events'))->toBeTrue();
});
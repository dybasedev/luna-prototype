<?php

use Dybasedev\LunaPrototype\Foundation\BusinessEvent\DefaultBusinessEventHandler;
use Dybasedev\LunaPrototype\Foundation\BusinessEvent\Models\BusinessEvent;

test('简单占位符替换', function () {
    $handler = new DefaultBusinessEventHandler();
    
    $event = new BusinessEvent();
    $event->formatter = '用户 {{name}} 的邮箱是 {{email}}';
    
    $handler->loadInstance($event);
    
    $payload = [
        'name' => '张三',
        'email' => 'zhangsan@example.com'
    ];
    
    $result = $handler->formatPayloadToText($payload);
    
    expect($result)->toBe('用户 张三 的邮箱是 zhangsan@example.com');
});

test('多层级占位符替换', function () {
    $handler = new DefaultBusinessEventHandler();
    
    $event = new BusinessEvent();
    $event->formatter = '用户 {{user.name}} 的邮箱是 {{user.email}}，地址是 {{user.address.city}}';
    
    $handler->loadInstance($event);
    
    $payload = [
        'user' => [
            'name' => '李四',
            'email' => 'lisi@example.com',
            'address' => [
                'city' => '北京',
                'district' => '朝阳区'
            ]
        ]
    ];
    
    $result = $handler->formatPayloadToText($payload);
    
    expect($result)->toBe('用户 李四 的邮箱是 lisi@example.com，地址是 北京');
});

test('深层嵌套占位符替换', function () {
    $handler = new DefaultBusinessEventHandler();
    
    $event = new BusinessEvent();
    $event->formatter = '订单 {{order.id}} 的商品 {{order.items.0.product.name}} 价格为 {{order.items.0.product.price}}';
    
    $handler->loadInstance($event);
    
    $payload = [
        'order' => [
            'id' => 'ORD001',
            'items' => [
                [
                    'product' => [
                        'name' => 'iPhone 15',
                        'price' => 5999.00
                    ],
                    'quantity' => 1
                ]
            ]
        ]
    ];
    
    $result = $handler->formatPayloadToText($payload);
    
    expect($result)->toBe('订单 ORD001 的商品 iPhone 15 价格为 5999');
});

test('缺失键的处理', function () {
    $handler = new DefaultBusinessEventHandler();
    
    $event = new BusinessEvent();
    $event->formatter = '用户 {{user.name}} 的电话是 {{user.phone}}';
    
    $handler->loadInstance($event);
    
    $payload = [
        'user' => [
            'name' => '王五'
            // phone 字段不存在
        ]
    ];
    
    $result = $handler->formatPayloadToText($payload);
    
    expect($result)->toBe('用户 王五 的电话是 ');
});

test('不同数据类型的处理', function () {
    $handler = new DefaultBusinessEventHandler();
    
    $event = new BusinessEvent();
    $event->formatter = '状态: {{status}}, 数量: {{count}}, 配置: {{config}}, 标签: {{tags}}';
    
    $handler->loadInstance($event);
    
    $payload = [
        'status' => true,
        'count' => 42,
        'config' => null,
        'tags' => ['PHP', 'Laravel', 'Vue']
    ];
    
    $result = $handler->formatPayloadToText($payload);
    
    expect($result)->toBe('状态: true, 数量: 42, 配置: , 标签: ["PHP","Laravel","Vue"]');
});

test('混合使用简单和嵌套占位符', function () {
    $handler = new DefaultBusinessEventHandler();
    
    $event = new BusinessEvent();
    $event->formatter = '{{title}}: {{user.name}} 在 {{created_at}} 创建了订单 {{order.id}}，金额 {{order.total.amount}} {{order.total.currency}}';
    
    $handler->loadInstance($event);
    
    $payload = [
        'title' => '新订单通知',
        'created_at' => '2024-01-01 12:00:00',
        'user' => [
            'name' => '赵六'
        ],
        'order' => [
            'id' => 'ORD002',
            'total' => [
                'amount' => 999.99,
                'currency' => 'CNY'
            ]
        ]
    ];
    
    $result = $handler->formatPayloadToText($payload);
    
    expect($result)->toBe('新订单通知: 赵六 在 2024-01-01 12:00:00 创建了订单 ORD002，金额 999.99 CNY');
});

test('没有格式化模板的情况', function () {
    $handler = new DefaultBusinessEventHandler();
    
    $event = new BusinessEvent();
    $event->display_name = '测试事件';
    $event->name = 'test_event';
    // 不设置 formatter
    
    $handler->loadInstance($event);
    
    $payload = ['key' => 'value'];
    
    $result = $handler->formatPayloadToText($payload);
    
    expect($result)->toBe('测试事件');
});

test('空格式化模板的处理', function () {
    $handler = new DefaultBusinessEventHandler();
    
    $event = new BusinessEvent();
    $event->formatter = '';
    
    $handler->loadInstance($event);
    
    $payload = ['key' => 'value'];
    
    $result = $handler->formatPayloadToText($payload);
    
    expect($result)->toBe('');
});

test('特殊字符占位符的处理', function () {
    $handler = new DefaultBusinessEventHandler();
    
    $event = new BusinessEvent();
    $event->formatter = '{{user.first-name}} {{user.last_name}} {{user.email@domain}}';
    
    $handler->loadInstance($event);
    
    $payload = [
        'user' => [
            'first-name' => 'John',
            'last_name' => 'Doe',
            'email@domain' => 'john@example.com'
        ]
    ];
    
    $result = $handler->formatPayloadToText($payload);
    
    expect($result)->toBe('John Doe john@example.com');
});
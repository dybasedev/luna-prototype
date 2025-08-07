# Foundation 组件

Foundation 是 Luna Prototype 的核心基础组件，提供了整个框架的基础架构和核心功能。它包含了多个子组件。该组件是所有组件默认必须依赖的组件。

## 组件构成

### 1. Handler（处理器）

处理器是 Luna Prototype 中用于执行特定业务逻辑的核心组件。处理器系统支持两种类型的处理器：

1. **实体处理器（Entity Handler）**：在数据库中有对应记录的处理器实例，支持持久化配置和状态管理
2. **纯定义处理器（Pure Handler）**：仅在代码中定义的处理器类，无需数据库记录

#### 核心类

- **BaseHandler**: 处理器基类，提供统一的处理器接口和配置管理功能
- **LunaHandler**: 处理器管理类，负责管理和维护所有注册的处理器
- **LunaHandlerConfigure**: 处理器配置类，用于注册和配置处理器
- **Handler (Model)**: 处理器实体模型，存储处理器的持久化配置
- **ModelHandler**: 模型处理器接口，实现该接口的处理器可以处理模型数据
- **WithModelHandler**: Trait，提供模型处理器的辅助功能，通过该 Trait 可以在模型中获取处理器实例
- **WithModelInstance**: Trait，为处理器提供模型实例管理功能，用于处理器反向获取模型实例

#### 处理器分组机制

处理器通过分组进行组织管理，每个处理器必须属于一个组。从架构设计上，处理器分为两种类型：

1. **实体处理器（Entity Handler）**：需要在数据库中创建实体记录，支持多实例和持久化配置
2. **纯处理器（Pure Handler）**：仅在代码中定义，不需要数据库记录，通常作为单例使用

每个处理器类通过 `requiresEntity()` 方法来声明自己的类型：

```php
// 方式一：注册处理器组和处理器
$configure = LunaHandlerConfigure::create()
    ->registerGroup(hash_code('payment'), 'payment', '支付处理')
    ->registerGroup(hash_code('auth'), 'auth', '认证处理')
    ->registerHandler('payment', AlipayHandler::class)
    ->registerHandler('payment', WechatPayHandler::class)
    ->registerHandler('auth', JwtAuthHandler::class)
    ->build();

// 方式二：使用 group() 方法自动处理组注册（推荐）
$configure = LunaHandlerConfigure::create()
    ->group('payment', '支付处理', function ($register) {
        $register->handler(AlipayHandler::class, 'payment.alipay');  // 带别名
        $register->handler(WechatPayHandler::class, 'payment.wechat');
    })
    ->group('auth', '认证处理', function ($register) {
        $register->handler(JwtAuthHandler::class, 'auth.jwt');
    })
    ->build();

// 方式三：单独设置别名
$configure = LunaHandlerConfigure::create()
    ->group('payment', '支付处理')
    ->handler('payment', AlipayHandler::class)
    ->alias('payment.alipay', AlipayHandler::class)  // 单独设置别名
    ->build();
```

使用 `group()` 方法时，系统会自动：
- 计算组的 hash_code
- 注册组（如果尚未注册）
- 在回调函数中，`handler()` 方法会自动将处理器归入当前组，无需手动指定组名

#### 实体处理器示例

实体处理器适用于需要动态配置或多实例的场景：

```php
// 1. 定义支付处理器基类
abstract class PaymentHandler extends BaseHandler
{
    abstract public function pay(array $order): array;
    abstract public function refund(string $transactionId, float $amount): bool;
}

// 2. 实现具体的支付处理器
class AlipayHandler extends PaymentHandler
{
    public function handlerName(): string
    {
        return '支付宝支付';
    }
    
    public function handlerDescription(): string
    {
        return '支付宝支付处理器，支持支付、退款等操作';
    }
    
    public function pay(array $order): array
    {
        $config = $this->getConfig();
        
        // 使用配置中的商户信息
        $appId = $config->get('app_id');
        $privateKey = $config->get('private_key');
        
        // 调用支付宝SDK进行支付
        return [
            'transaction_id' => 'ALIPAY' . uniqid(),
            'pay_url' => 'https://alipay.com/...',
        ];
    }
    
    public function refund(string $transactionId, float $amount): bool
    {
        // 退款逻辑
        return true;
    }
    
    // 指定配置仓库类（可选）
    public static function configurationRepository(): string
    {
        return PaymentConfigRepository::class;
    }
}

// 3. 注册处理器时可以提供别名
luna_handler_configure()->group('payment', '支付处理', function ($register) {
    $register->handler(AlipayHandler::class, 'payment.alipay');
    $register->handler(WechatPayHandler::class, 'payment.wechat');
});

// 4. 创建处理器实体（存储到数据库）
$alipayHandler = luna_handler()->createEntityHandler(
    group: 'payment',
    name: 'alipay-merchant1',
    handler: AlipayHandler::class,  // 也可以使用别名 'payment.alipay'
    config: new Repository([
        'app_id' => '2021001234567890',
        'private_key' => 'MIIEvQIBADANBgkqhkiG9w0BAQ...',
        'public_key' => 'MIIBIjANBgkqhkiG9w0BAQEFA...',
        'gateway' => 'https://openapi.alipay.com/gateway.do',
        'sandbox' => false,
    ]),
    displayName: '支付宝商户1',
    description: '用于主营业务的支付宝账户'
);

// 4. 使用处理器实体
$paymentHandler = luna_handler()->createHandlerInstance('alipay-merchant1');
$result = $paymentHandler->pay([
    'order_no' => 'ORDER123456',
    'amount' => 99.99,
    'subject' => '商品订单',
]);

// 5. 可以创建多个配置不同的实例
$alipayHandler2 = luna_handler()->createEntityHandler(
    group: 'payment',
    name: 'alipay-merchant2',
    handler: AlipayHandler::class,
    config: new Repository([
        'app_id' => '2021009876543210',
        // 不同的商户配置...
    ]),
    displayName: '支付宝商户2',
    description: '用于海外业务的支付宝账户'
);
```

#### 纯定义处理器示例

纯定义处理器适用于无需配置或单例的场景：

```php
// 1. 定义缓存处理器
class RedisCacheHandler extends BaseHandler
{
    public function handlerName(): string
    {
        return 'Redis缓存';
    }
    
    public function handlerDescription(): string
    {
        return 'Redis缓存处理器，提供高性能的缓存服务';
    }
    
    public function get(string $key): mixed
    {
        return Redis::get($key);
    }
    
    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        return Redis::setex($key, $ttl, serialize($value));
    }
    
    public function flush(): bool
    {
        return Redis::flushdb();
    }
}

// 2. 注册处理器
$configure = LunaHandlerConfigure::create()
    ->registerGroup(hash_code('cache'), 'cache', '缓存处理')
    ->registerHandler('cache', RedisCacheHandler::class)
    ->build();

// 3. 直接使用处理器类（无需创建实体）
$cacheHandler = app(RedisCacheHandler::class);
$cacheHandler->set('user:1', ['name' => '张三', 'age' => 25]);
$userData = $cacheHandler->get('user:1');
```

#### 处理器配置管理

处理器支持灵活的配置管理：

```php
// 自定义配置仓库
class PaymentConfigRepository extends Repository
{
    public function getGatewayUrl(): string
    {
        return $this->get('sandbox') ? 
            'https://sandbox.alipay.com/gateway.do' : 
            $this->get('gateway');
    }
    
    public function isProduction(): bool
    {
        return !$this->get('sandbox', false);
    }
}

// 在处理器中使用
class AlipayHandler extends PaymentHandler
{
    public static function configurationRepository(): string
    {
        return PaymentConfigRepository::class;
    }
    
    public function pay(array $order): array
    {
        /** @var PaymentConfigRepository $config */
        $config = $this->getConfig();
        $gatewayUrl = $config->getGatewayUrl();
        // ...
    }
}
```

#### 纯处理器示例

纯处理器适用于不需要动态配置、作为单例使用的场景：

```php
// 1. 定义纯处理器
class PermissionHandler extends BasePermissionHandler
{
    // 声明为纯处理器
    public static function requiresEntity(): bool
    {
        return false;
    }
    
    public function handlerName(): string
    {
        return '权限处理器';
    }
    
    public function handlerDescription(): string
    {
        return '基于策略的权限检查处理器';
    }
    
    public function check(
        PermissionSubject $subject,
        string $action,
        string $resource,
        array $context = []
    ): bool {
        // 权限检查逻辑
        return $this->checkPolicy($subject, $action, $resource, $context);
    }
}

// 2. 注册纯处理器（带别名）
$configure = LunaHandlerConfigure::create()
    ->group('permission', '权限', function ($register) {
        $register->handler(PermissionHandler::class, 'permission.default');
    })
    ->build();

// 3. 使用纯处理器（通过类名或别名）
$handler = luna_handler()->getPureHandler(PermissionHandler::class);
// 或通过别名获取
$handler = luna_handler()->getPureHandler('permission.default');

// 可以传入临时配置
$handler = luna_handler()->getPureHandler('permission.default', [
    'cache_ttl' => 300,
    'strict_mode' => true
]);

// 4. 获取指定组的所有纯处理器类
$pureHandlers = luna_handler()->getPureHandlerClasses('permission');

// 5. 查询处理器别名
$handlerClass = luna_handler()->getHandlerClassByAlias('permission.default');
// 返回: Dybasedev\LunaPrototype\Permission\Handlers\PermissionHandler
```

#### 处理器管理 API

```php
// 获取所有处理器组
$groups = luna_handler()->groups();

// 获取指定组的所有处理器类
$handlers = luna_handler()->handlers('payment');

// 获取所有实体处理器
$entities = luna_handler()->getAllEntityHandlers();

// 获取指定组的实体处理器
$paymentEntities = luna_handler()->entityHandlers('payment');

// 检查实体处理器是否存在
if (luna_handler()->existsEntityHandler('alipay-merchant1')) {
    // 处理器存在
}

// 获取单个实体处理器
$entity = luna_handler()->entityHandler('alipay-merchant1');
```

### 2. BusinessEvent（业务事件）

业务事件系统用于记录和处理系统中的重要操作，提供了灵活的事件处理和格式化机制。可以用于审计日志、操作历史记录、业务流程跟踪、通知推送等场景。

#### 核心类

- **BusinessEventHandler**: 业务事件处理器基类，继承自 BaseHandler，提供事件数据格式化功能
- **DefaultBusinessEventHandler**: 默认业务事件处理器，提供基础的模板替换格式化功能
- **LunaBusinessEvent**: 业务事件管理类，负责创建、管理和触发业务事件
- **LunaBusinessEventConfigure**: 业务事件配置类，用于注册事件组
- **BusinessEvent (Model)**: 业务事件实体模型，存储事件配置和格式化模板

#### 标准事件处理器机制

DefaultBusinessEventHandler 提供了最常用的事件处理场景——基于模板的文本格式化：

```php
// 1. 使用默认事件处理器
$configure = LunaBusinessEventConfigure::create()
    ->registerGroup(hash_code('user'), 'user', '用户事件')
    ->build();

// 2. 先创建处理器实体
luna_handler()->createEntityHandler(
    group: 'business-event',  // 业务事件使用特定的组
    name: 'user-event-handler',
    handler: DefaultBusinessEventHandler::class,
    displayName: '用户事件处理器'
);

// 3. 创建业务事件，使用模板格式化
$event = luna_business_event()->createBusinessEvent(
    name: 'user.registered',
    group: 'user',
    handler: 'user-event-handler',
    formatter: '新用户注册：{{username}}（{{email}}）于 {{time}} 注册成功',
    displayName: '用户注册事件'
);

// 4. 触发事件
$message = luna_business_event()->eventMessage('user.registered', [
    'username' => '张三',
    'email' => 'zhangsan@example.com',
    'time' => now()->format('Y-m-d H:i:s')
]);
// 输出: "新用户注册：张三（zhangsan@example.com）于 2024-01-01 12:00:00 注册成功"
```

#### 自定义事件处理器

通过继承 BusinessEventHandler 实现更复杂的格式化逻辑：

```php
// 1. 定义订单事件处理器
class OrderEventHandler extends BusinessEventHandler
{
    public function handlerName(): string
    {
        return '订单事件处理器';
    }
    
    public function handlerDescription(): string
    {
        return '处理订单相关的业务事件，支持多种格式化输出';
    }
    
    public function formatPayloadToText(array $payload, ?string $format = null, array $context = []): string
    {
        // 根据不同的格式返回不同的文本
        switch ($format) {
            case 'simple':
                return sprintf('订单 %s 已创建', $payload['order_no']);
                
            case 'detailed':
                return sprintf(
                    "订单详情：\n订单号：%s\n用户：%s\n金额：￥%.2f\n时间：%s",
                    $payload['order_no'],
                    $payload['user_name'],
                    $payload['amount'],
                    $payload['created_at']
                );
                
            case 'markdown':
                return sprintf(
                    "## 新订单通知\n\n- **订单号**: `%s`\n- **用户**: %s\n- **金额**: ￥%.2f\n- **时间**: %s",
                    $payload['order_no'],
                    $payload['user_name'],
                    $payload['amount'],
                    $payload['created_at']
                );
                
            default:
                // 使用模板格式化（如果事件配置了formatter）
                if ($this->modelInstance?->formatter) {
                    return parent::formatPayloadToText($payload, $format, $context);
                }
                
                // 默认格式
                return sprintf(
                    '用户 %s 创建了订单 %s，金额：￥%.2f',
                    $payload['user_name'],
                    $payload['order_no'],
                    $payload['amount']
                );
        }
    }
    
    public function formatPayloadToViewData(array $payload, ?string $format = null, array $context = []): ?array
    {
        // 根据不同的视图格式返回不同的数据结构
        switch ($format) {
            case 'list':
                return [
                    'order_no' => $payload['order_no'],
                    'amount' => $payload['amount'],
                    'status' => $payload['status'] ?? 'pending',
                    'created_at' => $payload['created_at'],
                ];
                
            case 'detail':
                return [
                    'title' => '订单详情',
                    'sections' => [
                        'basic' => [
                            'order_no' => $payload['order_no'],
                            'user_name' => $payload['user_name'],
                            'amount' => $payload['amount'],
                        ],
                        'items' => $payload['items'] ?? [],
                        'timeline' => [
                            'created_at' => $payload['created_at'],
                            'paid_at' => $payload['paid_at'] ?? null,
                        ]
                    ]
                ];
                
            case 'card':
                return [
                    'type' => 'order',
                    'title' => '新订单',
                    'subtitle' => $payload['user_name'],
                    'content' => sprintf('￥%.2f', $payload['amount']),
                    'footer' => $payload['created_at'],
                    'actions' => [
                        ['label' => '查看详情', 'url' => "/orders/{$payload['order_no']}"],
                        ['label' => '处理订单', 'url' => "/orders/{$payload['order_no']}/process"],
                    ]
                ];
                
            default:
                return [
                    'order' => $payload
                ];
        }
    }
}

// 2. 注册处理器和事件
$configure = LunaBusinessEventConfigure::create()
    ->registerGroup(hash_code('order'), 'order', '订单事件')
    ->build();

// 创建处理器实体
luna_handler()->createEntityHandler(
    group: 'business-event',
    name: 'order-handler',
    handler: OrderEventHandler::class,
    displayName: '订单事件处理器'
);

// 创建业务事件
$event = luna_business_event()->createBusinessEvent(
    name: 'order.created',
    group: 'order',
    handler: 'order-handler',
    formatter: '订单 {{order_no}} 创建成功', // 可选的默认模板
    displayName: '订单创建事件'
);

// 3. 使用不同格式输出
$payload = [
    'user_name' => '张三',
    'order_no' => 'ORD202401001',
    'amount' => 299.99,
    'created_at' => now()->format('Y-m-d H:i:s'),
    'items' => [
        ['name' => '商品A', 'price' => 199.99, 'quantity' => 1],
        ['name' => '商品B', 'price' => 100.00, 'quantity' => 1],
    ]
];

// 获取简单文本
$simpleText = luna_business_event()
    ->getAllEvents()
    ->where('name', 'order.created')
    ->first()
    ->handlerInstance()
    ->formatPayloadToText($payload, 'simple');

// 获取Markdown格式
$markdownText = luna_business_event()
    ->getAllEvents()
    ->where('name', 'order.created')
    ->first()
    ->handlerInstance()
    ->formatPayloadToText($payload, 'markdown');

// 获取卡片视图数据
$cardData = luna_business_event()
    ->getAllEvents()
    ->where('name', 'order.created')
    ->first()
    ->handlerInstance()
    ->formatPayloadToViewData($payload, 'card');
```

#### 业务事件管理 API

```php
// 获取所有事件组
$groups = luna_business_event()->groups();

// 获取指定组的事件
$orderEvents = luna_business_event()->events('order');

// 检查事件是否存在
if (luna_business_event()->existsBusinessEvent('order.created')) {
    // 事件存在
}

// 获取所有业务事件
$allEvents = luna_business_event()->getAllEvents();

// 直接触发事件并获取格式化消息
$message = luna_business_event()->eventMessage('order.created', [
    'user_name' => '李四',
    'order_no' => 'ORD202401002',
    'amount' => 199.99,
    'created_at' => now()
]);
```

#### 扩展业务事件处理器

可以基于抽象类创建特定领域的事件处理器：

```php
// 审计日志事件处理器
abstract class AuditEventHandler extends BusinessEventHandler
{
    public function formatPayloadToText(array $payload, ?string $format = null, array $context = []): string
    {
        $operator = $payload['operator'] ?? '系统';
        $action = $payload['action'] ?? '操作';
        $target = $payload['target'] ?? '对象';
        $ip = $payload['ip'] ?? '0.0.0.0';
        $time = $payload['time'] ?? now();
        
        return sprintf(
            "[%s] %s 从 %s 对 %s 执行了 %s",
            $time,
            $operator,
            $ip,
            $target,
            $action
        );
    }
    
    public function formatPayloadToViewData(array $payload, ?string $format = null, array $context = []): ?array
    {
        return [
            'type' => 'audit',
            'severity' => $payload['severity'] ?? 'info',
            'operator' => $payload['operator'],
            'action' => $payload['action'],
            'target' => $payload['target'],
            'changes' => $payload['changes'] ?? [],
            'metadata' => [
                'ip' => $payload['ip'],
                'user_agent' => $payload['user_agent'] ?? null,
                'session_id' => $payload['session_id'] ?? null,
            ],
            'timestamp' => $payload['time'],
        ];
    }
}

// 通知事件处理器
abstract class NotificationEventHandler extends BusinessEventHandler  
{
    abstract public function getChannels(array $payload): array;
    abstract public function shouldNotify(array $payload): bool;
    
    public function formatPayloadToText(array $payload, ?string $format = null, array $context = []): string
    {
        // 根据通知渠道格式化
        $channel = $context['channel'] ?? 'default';
        
        return match($channel) {
            'sms' => $this->formatForSms($payload),
            'email' => $this->formatForEmail($payload),
            'push' => $this->formatForPush($payload),
            default => parent::formatPayloadToText($payload, $format, $context)
        };
    }
    
    abstract protected function formatForSms(array $payload): string;
    abstract protected function formatForEmail(array $payload): string;
    abstract protected function formatForPush(array $payload): string;
}
```

### 3. Configuration（配置管理）

提供统一的配置管理功能，支持分组配置、版本控制和数据库存储。配置系统基于版本化设计，每次修改都会创建新版本，支持配置回滚和历史追踪。

#### 核心类

- **Repository**: 配置仓库基类，提供配置数据的存储和访问功能
- **LunaConfiguration**: 配置管理类，负责管理所有配置组
- **ConfigurationGroup**: 配置组类，处理特定组的配置项
- **LunaConfigurationConfigure**: 配置系统的配置类
- **Configuration (Model)**: 配置实体模型，存储配置元数据
- **ConfigurationValue (Model)**: 配置值模型，存储配置的实际值和版本信息

#### 配置仓库基础用法

```php
// 1. 创建配置仓库
$config = new Repository([
    'app_name' => 'Luna App',
    'debug' => true,
    'cache_ttl' => 3600,
    'features' => [
        'sms' => true,
        'email' => false,
        'push' => true
    ],
    'api' => [
        'timeout' => 30,
        'retry' => 3,
        'endpoints' => [
            'user' => 'https://api.example.com/user',
            'order' => 'https://api.example.com/order'
        ]
    ]
]);

// 2. 访问配置（支持点语法）
$appName = $config->get('app_name'); // "Luna App"
$smsEnabled = $config->get('features.sms'); // true
$userApi = $config->get('api.endpoints.user'); // "https://api.example.com/user"
$defaultTimeout = $config->get('api.timeout', 60); // 30（存在值）
$missing = $config->get('api.rate_limit', 100); // 100（使用默认值）

// 3. 修改配置
$config->set('debug', false);
$config->set('features.sms', false);
$config->set('api.endpoints.payment', 'https://api.example.com/payment');

// 4. 检查配置是否存在
if ($config->has('features.push')) {
    // 配置存在
}

// 5. 隐藏敏感配置
$config->setHidden(['api.secret', 'database.password']);
$safeConfig = $config->toArray(); // 敏感配置会被过滤
```

#### 配置组管理

配置组提供了更高级的配置管理功能，支持数据库存储和版本控制：

```php
// 1. 创建配置组
$systemGroup = luna_configuration()->group('system');

// 2. 创建新的配置项（存储到数据库）
$appConfig = $systemGroup->create(
    name: 'app',
    displayName: '应用配置',
    initialValues: [
        'name' => 'Luna Application',
        'version' => '1.0.0',
        'debug' => false,
        'timezone' => 'Asia/Shanghai',
        'locale' => 'zh-CN'
    ],
    description: '应用程序基础配置'
);

// 3. 获取配置仓库
$appRepo = $systemGroup->repository('app');

// 4. 读取配置
$appName = $systemGroup->get('app.name'); // "Luna Application"
$debug = $systemGroup->get('app.debug'); // false

// 5. 更新配置（支持事务）
$systemGroup->set('app.version', '1.0.1');
$systemGroup->set('app.debug', true);
$systemGroup->save(); // 保存到数据库，创建新版本

// 6. 使用多个配置项
$systemGroup->create('email', '邮件配置', [
    'driver' => 'smtp',
    'host' => 'smtp.example.com',
    'port' => 587,
    'username' => 'noreply@example.com',
    'password' => 'secret',
    'encryption' => 'tls',
    'from' => [
        'address' => 'noreply@example.com',
        'name' => 'Luna App'
    ]
]);

// 获取邮件配置
$mailDriver = $systemGroup->get('email.driver'); // "smtp"
$mailFrom = $systemGroup->get('email.from.address'); // "noreply@example.com"
```

#### 自定义配置仓库

可以创建自定义配置仓库来提供类型安全的配置访问：

```php
// 1. 定义应用配置仓库
class AppConfigRepository extends Repository
{
    public function getAppName(): string
    {
        return $this->get('name', 'Luna App');
    }
    
    public function getVersion(): string
    {
        return $this->get('version', '0.0.0');
    }
    
    public function isDebugMode(): bool
    {
        return $this->get('debug', false);
    }
    
    public function getTimezone(): string
    {
        return $this->get('timezone', 'UTC');
    }
    
    public function getMaintenanceMode(): array
    {
        return $this->get('maintenance', [
            'enabled' => false,
            'message' => '系统维护中',
            'retry_after' => 3600
        ]);
    }
}

// 2. 定义支付配置仓库
class PaymentConfigRepository extends Repository
{
    public function getGateways(): array
    {
        return $this->get('gateways', []);
    }
    
    public function getGatewayConfig(string $gateway): array
    {
        return $this->get("gateways.{$gateway}", []);
    }
    
    public function isGatewayEnabled(string $gateway): bool
    {
        return $this->get("gateways.{$gateway}.enabled", false);
    }
    
    public function getDefaultGateway(): string
    {
        return $this->get('default_gateway', 'alipay');
    }
    
    public function getTimeout(): int
    {
        return $this->get('timeout', 30);
    }
}

// 3. 注册自定义仓库
$configure = LunaConfigurationConfigure::create()
    ->bindRepository('system', 'app', AppConfigRepository::class)
    ->bindRepository('payment', 'config', PaymentConfigRepository::class)
    ->build();

// 4. 使用自定义仓库
/** @var AppConfigRepository $appConfig */
$appConfig = luna_configuration()->group('system')->repository('app');
$appName = $appConfig->getAppName();
$isDebug = $appConfig->isDebugMode();

/** @var PaymentConfigRepository $paymentConfig */
$paymentConfig = luna_configuration()->group('payment')->repository('config');
$alipayConfig = $paymentConfig->getGatewayConfig('alipay');
```

#### 配置版本管理

配置系统支持完整的版本控制，每次修改配置都会创建新版本：

```php
// 1. 获取当前版本ID
$currentVersionId = luna_configuration()
    ->group('system')
    ->getCurrentVersionId('app'); // 返回当前版本的SHA1 hash

// 2. 获取版本列表
$versions = luna_configuration()
    ->group('system')
    ->getVersionList('app');

// 返回格式：
// [
//     [
//         'version_id' => 'a1b2c3d4e5f6...',
//         'is_current' => true,
//         'created_at' => Carbon实例
//     ],
//     [
//         'version_id' => 'f6e5d4c3b2a1...',
//         'is_current' => false,
//         'created_at' => Carbon实例
//     ]
// ]

// 3. 获取指定版本的配置
$oldVersion = luna_configuration()
    ->group('system')
    ->getVersion('app', 'f6e5d4c3b2a1...');

// 可以像普通配置仓库一样使用
$oldAppName = $oldVersion->get('name');
$oldDebugMode = $oldVersion->get('debug');

// 4. 切换到指定版本（回滚）
$success = luna_configuration()
    ->group('system')
    ->switchVersion('app', 'f6e5d4c3b2a1...');

if ($success) {
    // 版本切换成功，缓存已自动清理
    // 后续获取的配置将是指定版本的内容
}

// 5. 版本对比示例
$systemGroup = luna_configuration()->group('system');
$currentVersion = $systemGroup->repository('app');
$previousVersion = $systemGroup->getVersion('app', 'f6e5d4c3b2a1...');

// 对比配置差异
$currentDebug = $currentVersion->get('debug'); // true
$previousDebug = $previousVersion->get('debug'); // false

// 6. 完整的版本管理流程示例
// 修改配置并创建新版本
$systemGroup->set('app.version', '2.0.0');
$systemGroup->set('app.features.new_feature', true);
$systemGroup->save(); // 自动创建新版本

// 获取最新的版本列表
$versions = $systemGroup->getVersionList('app');
$latestVersion = $versions[0]['version_id']; // 最新版本
$previousVersion = $versions[1]['version_id']; // 上一个版本

// 如果需要回滚
if ($needRollback) {
    $systemGroup->switchVersion('app', $previousVersion);
}

// 7. 使用配置记录直接操作（底层API）
$configRecord = luna_configuration()
    ->group('system')
    ->getConfigurationRecord('app');

// 查看所有版本
$allVersions = $configRecord->versions()
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

foreach ($versions as $version) {
    echo sprintf(
        "版本 %d - %s: %s\n",
        $version->id,
        $version->created_at,
        json_encode($version->value)
    );
}

// 3. 获取当前版本
$currentVersion = $configRecord->current;

// 4. 回滚到特定版本
$oldVersion = $configRecord->values()
    ->where('id', 123)
    ->first();

if ($oldVersion) {
    $configRecord->current_id = $oldVersion->id;
    $configRecord->save();
    
    // 清除缓存
    Cache::forget('config:system:app');
}
```

#### 高级功能

```php
// 1. 批量更新配置
$systemGroup = luna_configuration()->group('system');

// 开始批量更新
$appRepo = $systemGroup->repository('app');
$emailRepo = $systemGroup->repository('email');

$appRepo->set('version', '2.0.0');
$appRepo->set('features.new_ui', true);

$emailRepo->set('driver', 'ses');
$emailRepo->set('region', 'us-east-1');

// 一次性保存所有更改
$systemGroup->save();

// 2. 配置缓存控制
$configure = LunaConfigurationConfigure::create()
    ->cacheDriver('redis') // 使用 Redis 缓存
    ->cacheTtl(300) // 缓存 5 分钟
    ->build();

// 3. 配置导出和导入
// 导出配置
$configs = luna_configuration()
    ->group('system')
    ->repository('app')
    ->all();

file_put_contents('config_backup.json', json_encode($configs, JSON_PRETTY_PRINT));

// 导入配置
$importedConfigs = json_decode(file_get_contents('config_backup.json'), true);
$systemGroup->repository('app')->set(null, $importedConfigs);
$systemGroup->save();

// 4. 配置验证
class ValidatedConfigRepository extends Repository
{
    public function set(string|null $key, mixed $value, bool $overwrite = true): static
    {
        // 验证配置值
        if ($key === 'port' && (!is_int($value) || $value < 1 || $value > 65535)) {
            throw new InvalidArgumentException('端口号必须在 1-65535 之间');
        }
        
        if ($key === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('无效的邮箱地址');
        }
        
        return parent::set($key, $value, $overwrite);
    }
}
```

#### 配置管理 API

```php
// 获取配置组
$systemGroup = luna_configuration()->group('system');

// 检查配置是否存在
if ($systemGroup->exists('app')) {
    // 配置存在
}

// 获取配置值（支持点语法和默认值）
$value = $systemGroup->get('app.features.sms', false);

// 设置配置值
$systemGroup->set('app.features.sms', true);

// 保存更改
$systemGroup->save();

// 获取原始配置记录
$record = $systemGroup->getConfigurationRecord('app');
```

### 4. Exception（异常处理）

提供统一的异常处理机制，支持异常映射、自定义响应格式和灵活的异常报告。所有异常最终都会被转换为 LunaException，确保一致的错误响应格式。

#### 核心类

- **LunaException**: 统一的异常类，支持自定义显示消息、HTTP 状态码、行为控制和数据传递
- **BusinessException**: 业务异常基类，继承自 LunaException，专门用于业务逻辑中的预期异常
- **LunaExceptionConfigure**: 异常配置类，用于注册异常映射和配置异常处理行为
- **LunaExceptionMapperBuilder**: 异常映射构建器，提供流畅的接口来配置异常映射规则

#### 快速开始

```php
use Dybasedev\LunaPrototype\Foundation\Exception\LunaExceptionConfigure;
use Dybasedev\LunaPrototype\Foundation\Exception\Mappers\ExceptionMappers;

// 在 AppServiceProvider 的 boot 方法中
public function boot()
{
    /** @var LunaExceptionConfigure $configure */
    $configure = $this->app->make(LunaExceptionConfigure::class);
    
    // 配置 API 应用总是返回 JSON
    $configure->alwaysJsonRender();
    
    // 注册默认异常映射
    foreach (ExceptionMappers::defaults() as $mapper) {
        $configure->wrap($mapper);
    }
}
```

#### 使用 BusinessException

BusinessException 继承自 LunaException，提供了便捷的工厂方法来创建业务异常：

```php
use Dybasedev\LunaPrototype\Foundation\Exception\BusinessException;

// 基本用法
throw BusinessException::create('操作失败');

// 带状态码
throw BusinessException::make('资源不存在', 0, 404);

// 带额外数据
throw BusinessException::withInfo('验证失败', [
    'field' => 'email',
    'reason' => '邮箱格式不正确'
], 422);

// 使用预定义的工厂方法
throw BusinessException::insufficientBalance(100.0, 50.0);
throw BusinessException::insufficientStock(10, 3, ['name' => 'iPhone 15']);
throw BusinessException::notFound('订单', 'ORDER123');
throw BusinessException::forbidden('您没有权限执行此操作');
throw BusinessException::duplicateOperation('提交', 60);
```

#### 预定义异常映射

Foundation 提供了丰富的预定义异常映射模板，可以快速配置常见的 Laravel 异常：

##### 1. 基础异常映射（ExceptionMappers）

```php
use Dybasedev\LunaPrototype\Foundation\Exception\Mappers\ExceptionMappers;

// 应用所有默认映射
foreach (ExceptionMappers::defaults() as $mapper) {
    $configure->wrap($mapper);
}

// 或选择性地应用
$configure->wrap(ExceptionMappers::validation());        // 验证异常 (422)
$configure->wrap(ExceptionMappers::authentication());    // 认证异常 (401)
$configure->wrap(ExceptionMappers::authorization());     // 授权异常 (403)
$configure->wrap(ExceptionMappers::modelNotFound());     // 模型未找到 (404)
$configure->wrap(ExceptionMappers::throttle());          // 请求频率限制 (429)
```

##### 2. API 异常映射（ApiExceptionMappers）

```php
use Dybasedev\LunaPrototype\Foundation\Exception\Mappers\ApiExceptionMappers;

// 应用所有 API 映射
foreach (ApiExceptionMappers::all() as $mapper) {
    $configure->wrap($mapper);
}

// 包含：badRequest (400), conflict (409), unprocessableEntity (422) 等
```

##### 3. 业务异常映射（BusinessExceptionMappers）

```php
use Dybasedev\LunaPrototype\Foundation\Exception\Mappers\BusinessExceptionMappers;

// 注册通用业务异常处理
$configure->wrap(BusinessExceptionMappers::general());

// 使用预定义的业务场景
$scenarios = BusinessExceptionMappers::commonScenarios();
$configure->wrap($scenarios['insufficient_balance']);
$configure->wrap($scenarios['stock_shortage']);
```

#### 使用业务异常

BusinessException 继承自 LunaException，提供了更便捷的业务异常处理：

```php
use Dybasedev\LunaPrototype\Foundation\Exception\BusinessException;

// 基本用法
throw BusinessException::make('操作失败', 0, 400);

// 使用预定义的静态方法
throw BusinessException::insufficientBalance(100.00, 50.00);
throw BusinessException::insufficientStock(10, 5, ['name' => '商品A']);
throw BusinessException::notFound('订单', 'ORD123');
throw BusinessException::forbidden('您没有权限执行此操作');
throw BusinessException::validationFailed('输入错误', ['email' => ['邮箱格式不正确']]);

// 链式调用
throw BusinessException::make('服务暂时不可用')
    ->withHttpStatus(503)
    ->withData(['service' => 'payment', 'retry_after' => 60])
    ->withBehaviour(['action' => 'retry_later', 'delay' => 60]);
```

#### 自定义异常映射

使用 LunaExceptionMapperBuilder 创建自定义映射：

```php
// 简单映射
$configure->wrap(
    OrderNotFoundException::class,
    '订单不存在',
    404
);

// 使用构建器
$configure->wrap(
    LunaExceptionMapperBuilder::for(PaymentFailedException::class)
        ->message('支付失败，请重试')
        ->httpStatus(402)
        ->dontReport()
        ->behaviour(['action' => 'retry_payment'])
        ->data(fn($e) => [
            'order_id' => $e->getOrderId(),
            'amount' => $e->getAmount(),
        ])
);

// 动态处理
$configure->wrap(
    LunaExceptionMapperBuilder::for(RateLimitException::class)
        ->message(fn($e) => "请在 {$e->getRetryAfter()} 秒后重试")
        ->httpStatus(fn($e) => $e->isCritical() ? 503 : 429)
        ->data(fn($e) => ['retry_after' => $e->getRetryAfter()])
);
```

#### 继承 ExceptionMapperServiceProvider

创建自己的异常映射服务提供者：

```php
use Dybasedev\LunaPrototype\Foundation\Exception\Mappers\ExceptionMapperServiceProvider;

class AppExceptionServiceProvider extends ExceptionMapperServiceProvider
{
    // 自动注册所有默认映射
    protected bool $registerDefaults = true;
    
    // 排除某些默认映射
    protected array $excludeDefaults = [
        \Illuminate\Database\QueryException::class,
    ];
    
    // 默认映射的选项
    protected array $defaultOptions = [
        'debug' => false,
        'login_url' => '/auth/login',
    ];
    
    // 添加额外的映射
    protected function getMappers(): array
    {
        return [
            ...ApiExceptionMappers::all(),
            ExceptionMappers::queryException(true), // 开启调试模式
        ];
    }
}
```

#### 配置全局行为

```php
// 总是返回 JSON 响应（适用于纯 API 应用）
$configure->alwaysJsonRender();

// 自定义异常报告器
$configure->reporter(function (Throwable $e) {
    // 发送到外部监控服务
    Sentry::captureException($e);
    
    // 记录到特定通道
    Log::channel('exceptions')->error($e->getMessage(), [
        'exception' => get_class($e),
        'trace' => $e->getTraceAsString(),
    ]);
});
```

#### 异常响应格式

所有异常最终都会转换为统一的 JSON 响应格式：

```json
{
    "success": false,
    "message": "验证失败",
    "data": {
        "errors": {
            "email": ["邮箱格式不正确"],
            "password": ["密码长度至少8位"]
        }
    },
    "behaviour": {
        "action": "show_validation_errors"
    }
}
```

前端可以根据 `behaviour` 字段执行相应的操作，如跳转、刷新、显示特定提示等。

#### 创建自定义异常映射

使用 LunaExceptionMapperBuilder 创建自定义映射：

```php
use Dybasedev\LunaPrototype\Foundation\Exception\LunaExceptionMapperBuilder;

// 基础映射
$mapper = LunaExceptionMapperBuilder::for(CustomException::class)
    ->message('自定义错误消息')
    ->httpStatus(400)
    ->dontReport();

// 动态消息和数据
$mapper = LunaExceptionMapperBuilder::for(OrderException::class)
    ->message(fn($e) => "订单处理失败: {$e->getMessage()}")
    ->httpStatus(fn($e) => $e->isCritical() ? 500 : 400)
    ->data(fn($e) => [
        'order_id' => $e->getOrderId(),
        'error_code' => $e->getCode(),
    ])
    ->behaviour(fn($e) => [
        'action' => 'refresh_order',
        'order_id' => $e->getOrderId(),
    ]);

// 根据异常内容动态返回不同的值
$mapper = LunaExceptionMapperBuilder::for(ApiException::class)
    ->message(fn($e) => match(true) {
        $e->isTimeout() => '请求超时',
        $e->isRateLimited() => '请求频率过高',
        default => '外部服务异常'
    })
    ->httpStatus(fn($e) => match(true) {
        $e->isTimeout() => 504,
        $e->isRateLimited() => 429,
        default => 503
    });
```

#### 高级配置

##### 自定义异常报告器

```php
$configure->reporter(function (Throwable $e) {
    // 集成第三方错误跟踪服务
    if ($this->shouldReport($e)) {
        Sentry::captureException($e);
        
        // 发送关键错误通知
        if ($e instanceof CriticalException) {
            Notification::route('slack', config('slack.alerts'))
                ->notify(new CriticalErrorNotification($e));
        }
    }
});
```

##### 环境特定配置

```php
if ($this->app->environment('production')) {
    // 生产环境：隐藏敏感信息
    $configure->wrap(
        ExceptionMappers::queryException(false) // 不显示 SQL 详情
    );
} else {
    // 开发环境：显示详细错误
    $configure->wrap(
        ExceptionMappers::queryException(true) // 显示 SQL 详情
    );
}
```

##### 完整配置示例

查看 `examples/Exception/ExceptionConfigExample.php` 了解完整的配置示例，包括：
- 如何组织异常映射
- 如何创建自定义映射
- 如何集成第三方服务
- 如何根据环境调整配置

### 5. Installation（安装器）

提供应用程序的安装和初始化功能，支持依赖管理、分步安装、数据初始化等场景。

#### 核心类

- **Installation**: 安装器基类，定义安装的标准接口
- **LunaApplicationConfigure**: 应用程序配置类，管理安装器注册

#### 功能特性

1. **依赖管理**: 通过 `$installations` 属性定义前置依赖的安装器
2. **输出支持**: 集成控制台输出，提供安装进度反馈
3. **事务保护**: 所有安装操作在事务中执行，确保数据一致性
4. **顺序执行**: 根据依赖关系自动确定安装顺序

#### 使用示例

```php
// 创建基础安装器
class SystemInstallation extends Installation
{
    public function install(): void
    {
        $this->writeln('=> 初始化系统配置...');
        
        // 创建默认配置
        Configuration::create([
            'name' => 'system.version',
            'value' => ['version' => '1.0.0']
        ]);
        
        // 创建默认用户组
        UserGroup::create([
            'name' => 'administrators',
            'display_name' => '管理员组'
        ]);
        
        $this->writeln('   系统配置初始化完成');
    }
}

// 创建带依赖的安装器
class BusinessInstallation extends Installation
{
    // 声明依赖的前置安装器
    protected array $installations = [
        SystemInstallation::class,
        PermissionInstallation::class,
    ];
    
    public function install(): void
    {
        $this->writeln('=> 初始化业务数据...');
        
        // 创建业务相关的初始数据
        // 此时系统配置和权限已经安装完成
        
        $this->writeln('   业务数据初始化完成', 'v');
    }
}

// 注册安装器
$configure = LunaApplicationConfigure::create()
    ->installation(SystemInstallation::class)
    ->installation(PermissionInstallation::class)
    ->installation(BusinessInstallation::class)
    ->build();

// 执行安装（通过 app:install 命令）
// php artisan app:install
```

#### 注意事项

1. **避免 DDL 操作**: 安装器中不要包含数据库结构变更操作（CREATE TABLE 等），这些应该在迁移文件中处理
2. **幂等性**: 安装逻辑应该设计为可重复执行，避免重复安装导致的问题
3. **错误处理**: 安装失败时会自动回滚事务，确保数据一致性
4. **依赖循环**: 避免安装器之间的循环依赖

#### 安装状态管理

系统通过 `.luna-installed` 文件跟踪安装状态，该文件包含：
- 安装时间
- 应用版本
- 环境信息

该文件会自动添加到 `.gitignore` 中，避免在版本控制中提交。

#### 重新安装流程

1. **检测已安装状态**
2. **交互式确认**（或使用 --force 跳过）
3. **备份现有数据**（可选）
4. **清理现有数据**（migrate:fresh）
5. **执行新安装**
6. **更新安装标识**

### 6. Backupable（可备份对象）

提供数据备份和恢复功能，支持配置数据迁移、业务数据同步等场景。

#### 核心类

- **Backupable**: 可备份对象接口，定义备份和恢复的标准契约
- **BackupableModel**: Model 的备份功能 Trait，提供默认实现
- **BackupableProvider**: 备份对象提供者接口
- **BackupableDirectoryProvider**: 目录扫描提供者，自动发现可备份对象
- **BackupableManualProvider**: 手动注册提供者

#### 使用示例

```php
// 实现可备份模型
class Configuration extends Model implements Backupable
{
    use NamedId, BackupableModel;
    
    protected $table = 'luna_configurations';
    protected $fillable = ['name', 'value'];
    protected $casts = ['value' => 'array'];
}

// 配置备份对象
$configure = LunaApplicationConfigure::create()
    ->registerBackupable(Configuration::class)
    ->registerBackupable(Handler::class)
    ->addBackupableDirectory(app_path('Models'))
    ->build();

// 导出备份
$backup = luna_app()->exportBackup();
file_put_contents('backup.dat', $backup);

// 导入备份
$backup = file_get_contents('backup.dat');
$result = luna_app()->importBackup($backup);
```

### 6. 核心功能

#### SessionHolder 接口

定义了会话持有者的标准接口，用于标识操作者身份：

```php
interface SessionHolder
{
    public function getOperatorId(): int;
    public function getOperatorType(): int; 
    public function getOperatorTypeName(): string;
    public function getSessionHolderContext(): ?array;
}
```

#### LunaModule 基类

所有 Luna 模块的基类，提供模块化的基础功能。

#### LunaApplication

Luna 应用程序主类，继承自 LunaModule，作为整个框架的入口点。

#### LunaServiceProvider

Laravel 服务提供者，负责注册 Foundation 组件的所有服务。支持模块化注册和扩展。

## 数据库迁移

Foundation 组件包含以下数据库表：

1. **luna_configurations** - 存储系统配置
2. **luna_configuration_values** - 配置值版本控制
3. **luna_handlers** - 存储处理器实体
4. **luna_business_events** - 存储业务事件配置

发布并运行迁移：

```bash
# 发布迁移文件到项目
php artisan vendor:publish --provider="Dybasedev\LunaPrototype\Foundation\LunaServiceProvider" --tag=migrations

# 运行迁移
php artisan migrate
```

## 命令行工具

Foundation 提供了以下 Artisan 命令：

- `app:current` - 显示当前环境文件信息
- `app:env` - 切换环境配置文件
- `app:install` - 安装应用程序（支持重新安装和自动备份）
- `app:backup` - 管理数据备份（导出/导入/查看）
- `app:publish-models` - 发布 Luna 模块的模型到应用程序

### app:install 命令详细说明

安装命令现在支持以下功能：

1. **安装标识文件**：`.luna-installed` 文件记录安装信息
2. **自动 .gitignore 更新**：自动将安装标识文件加入 .gitignore
3. **重新安装支持**：
   - 自动检测已安装状态
   - 交互式确认重新安装
   - 自动备份现有数据

命令选项：
```bash
# 基础安装
php artisan app:install

# 强制重新安装（跳过确认）
php artisan app:install --force

# 重新安装时跳过备份
php artisan app:install --force --skip-backup

# 指定备份文件名
php artisan app:install --force --backup-file=my-backup.dat
```

### app:publish-models 命令详细说明

发布模型命令用于将 Luna 模块中的模型发布到应用程序的 `App\Models` 目录。发布的模型将继承原始模块模型，并保留字段注释以便 IDE 识别。

#### 基本用法

```bash
# 发布所有模块的模型
php artisan app:publish-models

# 发布指定模块的模型
php artisan app:publish-models --module=luna.assets-account --module=luna.trade

# 强制覆盖已存在的文件
php artisan app:publish-models --force

# 预览模式（不实际创建文件）
php artisan app:publish-models --dry-run

# 自动为冲突的模型添加 Luna 前缀
php artisan app:publish-models --prefix
```

#### 处理同名模型

当目标目录中已存在同名模型时，命令提供以下处理方式：

1. **交互式选择**（默认）：命令会询问您如何处理
   - 跳过此模型
   - 添加 Luna 前缀（例如：`AssetsAccount` → `LunaAssetsAccount`）
   - 覆盖现有文件

2. **自动添加前缀**：使用 `--prefix` 选项
   ```bash
   php artisan app:publish-models --prefix
   ```
   所有冲突的模型都会自动添加 `Luna` 前缀

3. **强制覆盖**：使用 `--force` 选项
   ```bash
   php artisan app:publish-models --force
   ```
   直接覆盖已存在的文件

4. **非交互模式**：使用 `--no-interaction` 选项
   ```bash
   php artisan app:publish-models --no-interaction
   ```
   在非交互模式下，默认跳过已存在的文件

#### 生成的模型示例

普通情况下生成的模型：
```php
<?php

namespace App\Models;

use Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccount as BaseAssetsAccount;

/**
 * AssetsAccount Model
 * 
 * 继承自 Luna 模块的 AssetsAccount 模型
 * 
 * @property int $id
 * @property string $name
 * @property float $balance
 * 
 * @package App\Models
 */
class AssetsAccount extends BaseAssetsAccount
{
    //
}
```

使用前缀时生成的模型：
```php
<?php

namespace App\Models;

use Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccount;

/**
 * LunaAssetsAccount Model
 * 
 * 继承自 Luna 模块的 AssetsAccount 模型
 * 
 * @property int $id
 * @property string $name
 * @property float $balance
 * 
 * @package App\Models
 */
class LunaAssetsAccount extends AssetsAccount
{
    //
}
```

## 配置

### 注册服务提供者

在 `config/app.php` 中注册：

```php
'providers' => [
    // ...
    Dybasedev\LunaPrototype\Foundation\LunaServiceProvider::class,
],
```

### 扩展服务提供者

创建自定义的服务提供者：

```php
class AppServiceProvider extends LunaServiceProvider
{
    public function customRegister(): void
    {
        // 注册自定义模块
        $this->registerModule(
            MyModuleConfigure::create()->build()
        );
    }
    
    public function customBoot(): void
    {
        // 自定义启动逻辑
    }
}
```

## 辅助函数

Foundation 提供了以下辅助函数：

- `hash_code(string $str): int` - 将字符串转换为整数哈希值
- `short_hash_code(string $str): int` - 生成 0-254 范围内的短哈希码
- `luna_config(?string $group = null)` - 获取配置对象或配置组
- `luna_response(...)` - 生成标准化的 JSON 响应
- `err(Throwable|string $throwable)` - 生成错误响应
- `ok(...)` - 生成成功响应
- `luna_module_configure(string $configure)` - 获取模块配置对象
- `luna_handler()` - 获取 LunaHandler 实例
- `luna_exception_mapper(string $exceptionClass)` - 创建异常映射器
- `luna_business_event()` - 获取 LunaBusinessEvent 实例
- `luna_app()` - 获取 LunaApplication 实例
- `luna_registered_modules()` - 获取所有已注册的模块配置对象数组

## 最佳实践

1. **处理器设计**
   - 每个处理器应该只负责一个特定的业务领域
   - 使用配置仓库管理处理器的运行时配置
   - 合理使用处理器组进行分类管理

2. **业务事件**
   - 为重要的业务操作创建对应的事件
   - 事件载荷应该包含足够的上下文信息
   - 使用事件组对相关事件进行分类

3. **错误处理**
   - 使用 LunaException 或其子类抛出业务异常
   - 利用异常映射器统一处理第三方异常
   - 记录必要的错误日志

4. **性能优化**
   - 合理使用缓存减少数据库查询
   - 处理器实例会被缓存，避免在处理器中存储请求相关的状态
   - 业务事件的格式化应该尽量高效

## 扩展开发

Foundation 组件提供了良好的扩展性：

1. **自定义处理器**：继承 BaseHandler 创建自定义处理器
2. **自定义事件处理器**：继承 BusinessEventHandler 创建自定义事件处理器  
3. **自定义配置仓库**：继承 Repository 创建特定功能的配置仓库
4. **自定义异常**：继承 LunaException 创建业务相关的异常类
5. **自定义模块**：继承 LunaModule 和 LunaModuleConfigure 创建新模块

## 依赖关系

Foundation 组件是 Luna Prototype 的核心组件，其他所有组件都依赖于它。主要依赖：

- Laravel Framework 11.x
- PHP 8.4+

## 注意事项

1. Foundation 组件的修改可能会影响所有其他组件，请谨慎操作
2. 处理器和事件的注册应该在应用启动时完成
3. 避免在运行时动态注册大量处理器或事件，这可能影响性能
4. 定期清理不再使用的处理器实体和事件配置
5. 使用 hash_code 函数时注意哈希冲突的可能性
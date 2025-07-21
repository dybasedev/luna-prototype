# Foundation 组件

Foundation 是 Luna Prototype 的核心基础组件，提供了整个框架的基础架构和通用功能。它包含了多个子模块，为其他组件提供统一的接口和工具。

## 组件构成

### 1. Handler（处理器）

处理器是 Luna Prototype 中用于执行特定业务逻辑的核心组件。通过统一的处理器接口，可以实现业务逻辑的模块化和可扩展性。

#### 核心类

- **BaseHandler**: 处理器基类，提供统一的处理器接口和配置管理功能
- **LunaHandler**: 处理器管理类，负责管理和维护所有注册的处理器
- **LunaHandlerConfigure**: 处理器配置类，用于注册和配置处理器
- **ModelHandler**: 模型处理器接口，用于与数据模型交互的处理器
- **WithModelHandler**: Trait，提供模型处理器的辅助功能
- **WithModelInstance**: Trait，为处理器提供模型实例管理功能

#### 使用示例

```php
// 创建自定义处理器
class UserAuthHandler extends BaseHandler
{
    public function handlerName(): string
    {
        return 'user-auth';
    }
    
    public function handlerDescription(): string
    {
        return '用户认证处理器';
    }
    
    public function authenticate(array $credentials): bool
    {
        // 认证逻辑
    }
}

// 注册处理器
$configure = LunaHandlerConfigure::create()
    ->registerHandler(UserAuthHandler::class)
    ->registerGroup(hash_code('auth'), 'auth', '认证')
    ->build();

// 创建处理器实体
$handler = luna_handler()->createEntityHandler(
    group: 'auth',
    name: 'main-auth', 
    handler: UserAuthHandler::class,
    displayName: '主认证处理器'
);

// 使用处理器
$instance = luna_handler()->createHandlerInstance('main-auth');
$result = $instance->authenticate(['username' => 'test', 'password' => '123456']);
```

### 2. BusinessEvent（业务事件）

业务事件系统用于记录和处理系统中的重要操作，可以用于审计日志、操作历史记录、业务流程跟踪等场景。

#### 核心类

- **BusinessEventHandler**: 业务事件处理器基类，提供事件格式化功能
- **LunaBusinessEvent**: 业务事件管理类，负责管理和维护系统中的业务事件
- **LunaBusinessEventConfigure**: 业务事件配置类
- **DefaultBusinessEventHandler**: 默认业务事件处理器实现

#### 使用示例

```php
// 创建自定义事件处理器
class OrderEventHandler extends BusinessEventHandler
{
    public function handlerName(): string
    {
        return 'order-event';
    }
    
    public function handlerDescription(): string
    {
        return '订单事件处理器';
    }
    
    public function formatPayloadToText(array $payload, ?string $format = null, array $context = []): string
    {
        return sprintf(
            '用户 %s 创建了订单 %s，金额：%s',
            $payload['user_name'],
            $payload['order_no'],
            $payload['amount']
        );
    }
    
    public function formatPayloadToViewData(array $payload, ?string $format = null, array $context = []): ?array
    {
        return [
            'title' => '新订单',
            'user' => $payload['user_name'],
            'order_no' => $payload['order_no'],
            'amount' => $payload['amount'],
            'time' => $payload['created_at'],
        ];
    }
}

// 注册事件组
$configure = LunaBusinessEventConfigure::create()
    ->registerGroup(hash_code('order'), 'order', '订单事件')
    ->build();

// 创建业务事件
$event = luna_business_event()->createBusinessEvent(
    name: 'order.created',
    group: 'order',
    handler: 'order-handler',
    formatter: OrderEventHandler::class,
    displayName: '订单创建'
);

// 获取事件消息
$message = luna_business_event()->eventMessage('order.created', [
    'user_name' => '张三',
    'order_no' => 'ORD202401001',
    'amount' => 99.99,
    'created_at' => now()
]);
```

### 3. Configuration（配置管理）

提供统一的配置管理功能，支持分组配置、版本控制和数据库存储。

#### 核心类

- **Repository**: 配置仓库基类，提供配置数据的存储和访问功能
- **LunaConfiguration**: 配置管理类，负责管理配置组
- **ConfigurationGroup**: 配置组类，处理特定组的配置
- **LunaConfigurationConfigure**: 配置系统的配置类

#### 使用示例

```php
// 创建配置仓库
$config = new Repository([
    'debug' => true,
    'cache_ttl' => 3600,
    'features' => [
        'sms' => true,
        'email' => false
    ]
]);

// 访问配置
$debug = $config->get('debug'); // true
$smtpHost = $config->get('smtp.host', 'localhost'); // 使用默认值
$config->set('cache_ttl', 7200);

// 使用配置组
$dbConfig = luna_config('database');
$dbConfig->set('host', '127.0.0.1');
$dbConfig->save(); // 保存到数据库
```

### 4. Exception（异常处理）

提供统一的异常处理机制，支持异常映射、自定义响应和错误报告。

#### 核心类

- **LunaException**: Luna Prototype 的基础异常类
- **LunaExceptionConfigure**: 异常系统配置类
- **LunaExceptionMapperBuilder**: 异常映射器构建器

#### 使用示例

```php
// 抛出业务异常
throw (new LunaException('用户未找到'))
    ->withDisplayMessage('该用户不存在')
    ->withHttpStatus(404)
    ->withData(['user_id' => 123])
    ->withBehaviour(['redirect' => '/users']);

// 配置异常映射
$configure = LunaExceptionConfigure::create()
    ->registerExceptionMapper(
        luna_exception_mapper(ValidationException::class)
            ->message('输入数据验证失败')
            ->httpStatus(422)
            ->data(fn($e) => ['errors' => $e->errors()])
    )
    ->build();
```

### 5. 核心功能

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

运行迁移：

```bash
php artisan migrate --path=vendor/dybasedev/luna-prototype/src/Foundation/migrations
```

## 命令行工具

Foundation 提供了以下 Artisan 命令：

- `luna:app:current` - 显示当前应用信息
- `luna:app:environment` - 显示环境信息
- `luna:app:install` - 安装应用程序

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
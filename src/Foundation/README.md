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

运行迁移：

```bash
php artisan migrate --path=vendor/dybasedev/luna-prototype/src/Foundation/migrations
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

发布模型命令用于将 Luna 模块中的模型发布到应用程序：

```bash
# 发布所有模块的模型
php artisan app:publish-models

# 发布指定模块的模型
php artisan app:publish-models --module=luna.assets-account --module=luna.trade

# 强制覆盖已存在的文件
php artisan app:publish-models --force

# 预览模式（不实际创建文件）
php artisan app:publish-models --dry-run
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
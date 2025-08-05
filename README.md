# Luna Prototype

基于 Laravel 12.0 的快速业务原型开发框架

## 概述

Luna Prototype 是一个基于 Laravel 12.0 和 PHP 8.4 的模块化快速业务原型开发框架。框架的核心理念是**减少不必要的重复功能开发**，通过提供**相对原子化的组件**，让开发者可以根据具体业务需求进行**扩展、调整和组合使用**。

### 设计理念

- **原子化组件**: 每个模块都设计为功能单一、职责明确的原子化组件，可独立使用
- **避免重复开发**: 提供常见业务场景的基础实现，减少从零开始的重复工作
- **灵活组合**: 模块间松耦合，可根据项目需求自由组合和扩展
- **渐进式集成**: 可以选择性地集成需要的模块，不强制使用全套功能
- **扩展优先**: 通过处理器模式、配置系统等机制，优先支持扩展而非修改核心代码

Luna Prototype 特别适合快速原型开发、概念验证、初创项目和需要快速迭代的业务场景。

## 主要特性

### 基础架构特性

- **原子化模块架构**: 每个模块都是独立的原子化组件，可按需选择和组合
- **配置管理系统**: 支持版本控制的灵活配置存储和管理机制
- **处理器扩展模式**: 基于处理器的业务逻辑扩展点，支持插件化开发
- **业务事件系统**: 业务操作事件定义和用户友好的描述格式化机制
- **异常处理机制**: 统一的异常定义、映射和处理框架
- **安装器框架**: 模块化的安装流程管理
- **备份恢复机制**: 应用状态的备份和恢复功能

### 业务组件特性

- **资产账户系统**: 提供多层级账户类型、余额管理、原子化操作等功能
- **交易系统**: 完整的交易流程管理，包括订单、支付、退款等功能
- **会员体系框架**: 可扩展的会员等级、里程碑系统和权益管理
- **权限系统**: 基于策略的权限管理，支持角色、用户组和灵活的权限分配
- **单位转换系统**: 支持多种单位类型转换，包括货币、度量衡等，支持动态汇率
- **对象持有系统**: 灵活的对象持有关系管理，支持签到、购买限制、抽奖等场景
- **调度任务系统**: 灵活的定时任务和后台作业管理框架
- **UI组件抽象层**: 前端无关的表单字段和数据展示组件抽象

## 原子化组件优势

### 按需使用
```php
// 只需要账户系统
class AppServiceProvider extends LunaServiceProvider 
{
    public function customRegister(): void 
    {
        $this->registerModule(LunaAssetsAccountConfigure::create()->build());
    }
}

// 需要完整功能
class AppServiceProvider extends LunaServiceProvider 
{
    public function customRegister(): void 
    {
        $this->registerModule(LunaAssetsAccountConfigure::create()->build());
        $this->registerModule(LunaScheduleConfigure::create()->build());
        $this->registerModule(LunaMembershipConfigure::create()->build());
        $this->registerModule(LunaUnitConversionConfigure::create()->build());
        $this->registerModule(LunaTradeConfigure::create()->build());
        $this->registerModule(LunaHoldingObjectConfigure::create()->build());
        $this->registerModule(LunaPermissionConfigure::create()->build());
    }
}
```

### 灵活扩展
```php
// 扩展账户类型处理器
$this->extendModule(function() {
    return LunaHandlerConfigure::create()
        ->group('account_handlers', '账户处理器', function($register) {
            $register->handler(CryptoWalletHandler::class);
            $register->handler(PointsHandler::class);
            $register->handler(CouponHandler::class);
        })
        ->build();
});

// 自定义业务事件格式化
$this->extendModule(function() {
    return LunaBusinessEventConfigure::create()
        ->group('payment_events', '支付事件', function($register) {
            $register->handler(PaymentEventHandler::class);
        })
        ->build();
});
```

## 核心概念

### 模块组件（Module）

Luna Prototype 的核心是**组件化设计**。每个组件都是一个完整、独立的功能单元模块，具有清晰的职责边界和标准化的结构。

#### 组件的标准结构

每个 Luna 组件通常包含三个核心部分：

1. **配置器（Configure）** - 组件的核心，负责服务注册、依赖管理和组件配置
2. **访问入口类（Module）** - 组件的业务逻辑封装和 API 接口
3. **服务提供者（ServiceProvider）** - 资源发布器（可选）

```php
// 组件的典型结构
namespace Dybasedev\LunaPrototype\YourComponent;

// 1. 配置器 - 处理组件的注册和启动逻辑
class LunaYourComponentConfigure extends LunaModuleConfigure { }

// 2. 访问入口类 - 提供组件的业务 API
class LunaYourComponent extends LunaModule { }

// 3. 服务提供者 - 负责资源文件的发布
class LunaYourComponentServiceProvider extends ServiceProvider { }
```

#### 组件的概念

组件是 Luna Prototype 的基本构建单元，每个组件：

- **独立性**：可以独立安装、配置和使用
- **原子性**：专注于单一的业务领域或功能
- **可组合**：可以与其他组件自由组合使用
- **可扩展**：提供标准化的扩展点和配置选项

### 组件的配置器（Luna Module Configure）

配置器是组件的核心，继承自 `LunaModuleConfigure` 基类，负责：

#### 定义组件身份
```php
class LunaAssetsAccountConfigure extends LunaModuleConfigure
{
    public function name(): string
    {
        return 'luna.assets-account';  // 组件唯一标识
    }
    
    public function dependencies(): array
    {
        return ['luna.foo'];  // 声明依赖的其他组件，此处仅作演示
    }
}
```

#### 配置选项管理
```php
class LunaAssetsAccountConfigure extends LunaModuleConfigure
{
    // 配置属性
    protected(set) string $accountModel = AssetsAccount::class;
    protected(set) string $accountTypeModel = AssetsAccountType::class;
    
    // 配置方法（流式接口，且强烈建议使用该方式）
    public function useAccountModel(string $class): static
    {
        $this->accountModel = $class;
        return $this;
    }
}
```

#### 服务注册

配置器负责组件的服务注册，这是 Luna Prototype 的一个重要设计理念：

```php
public function register(Container $container): void
{
    // 注册单例服务
    $container->singleton('luna.assets-account', function ($app) {
        return new LunaAssetsAccount(
            $this,
            $app->make('cache.store'),
            $app->make(LunaHandler::class)
        );
    });
    
    // 注册别名
    $container->alias('luna.assets-account', LunaAssetsAccount::class);
}
```

> **重要**：与传统 Laravel 包不同，Luna 组件的 `register()` 和 `boot()` 逻辑都在配置器（Configure）类中实现，而不是在服务提供者中。

### 服务提供者的作用

在 Luna Prototype 中，服务提供者（ServiceProvider）专注于**资源文件的发布**，这是它的主要职责：

```php
class LunaAssetsAccountServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // 发布数据库迁移文件
        $this->publishesMigrations([
            __DIR__ . '/migrations' => database_path('migrations'),
        ]);
        
        // 如果组件有配置文件、语言文件等其他资源
        // $this->publishes([
        //     __DIR__ . '/config/assets-account.php' => config_path('luna/assets-account.php'),
        // ], 'luna-assets-account-config');
        
        // $this->publishes([
        //     __DIR__ . '/lang' => lang_path('luna/assets-account'),
        // ], 'luna-assets-account-lang');
    }
}
```

#### 为什么要分离服务提供者？

1. **清晰的发布管理**：使用 `php artisan vendor:publish` 时，可以清楚地看到每个组件可发布的资源
2. **按需发布**：开发者可以选择性地发布特定组件的资源文件
3. **保持配置器纯粹**：配置器专注于组件逻辑，服务提供者专注于资源发布

#### 使用资源发布

```bash
# 查看可发布的资源
php artisan vendor:publish

# 发布特定组件的迁移文件
php artisan vendor:publish --provider="Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccountServiceProvider"

# 使用标签发布（如果定义了标签）
php artisan vendor:publish --tag=luna-trade-migrations
```

### 组件的访问入口类

访问入口类继承自 `LunaModule` 基类，提供组件的主要 API 接口：

```php
class LunaAssetsAccount extends LunaModule
{
    // 提供业务方法
    public function createAccountType(
        string $name,
        string $handler,
        ?string $displayName = null
    ): AssetsAccountType {
        // 业务逻辑实现
    }
    
    // 获取用户账户
    public function ownerAccount(
        Model $owner,
        string $accountType
    ): AssetsAccount {
        // 业务逻辑实现
    }
}
```

访问组件的三种方式：

```php
// 1. 依赖注入
public function __construct(LunaAssetsAccount $assetsAccount) { }

// 2. 服务容器
$assetsAccount = app('luna.assets-account');

// 3. 辅助函数
$assetsAccount = luna_assets_account();
```

### 组件的注册流程

Luna Prototype 采用了独特的组件注册机制，通过继承 `LunaServiceProvider` 来管理所有组件：

```php
// app/Providers/AppServiceProvider.php
class AppServiceProvider extends LunaServiceProvider
{
    public function customRegister(): void
    {
        // 注册组件
        $this->registerModule(
            LunaAssetsAccountConfigure::create()
                ->useAccountModel(CustomAccount::class)  // 自定义配置
                ->build()
        );
        
        // 扩展现有组件
        $this->extendModule(function() {
            return LunaHandlerConfigure::create()
                ->group('payment', '支付处理器', function($register) {
                    $register->handler(AlipayHandler::class);
                })
                ->build();
        });
    }
}
```

#### 注册流程详解

1. **继承 LunaServiceProvider**：您的 `AppServiceProvider` 需要继承 `LunaServiceProvider` 而不是 Laravel 的 `ServiceProvider`
2. **配置组件**：在 `customRegister()` 方法中使用 `registerModule()` 注册需要的组件
3. **自动注册服务提供者**：框架会自动注册组件配置器中声明的 ServiceProvider，无需手动添加到 `config/app.php`
4. **处理依赖关系**：框架自动检查组件间的依赖关系，确保正确的加载顺序
5. **执行生命周期方法**：依次执行配置器的 `register()` 方法、ServiceProvider 的注册、配置器的 `boot()` 方法

#### 自动服务提供者注册机制

Luna Prototype 的一个重要特性是**自动注册服务提供者**，这大大减少了业务开发者的样板代码：

```php
// 在组件的配置器中声明服务提供者
class LunaAssetsAccountConfigure extends LunaModuleConfigure
{
    public function serviceProvider(): ?string
    {
        return LunaAssetsAccountServiceProvider::class;
    }
}

// LunaServiceProvider 会自动注册这个服务提供者
// 无需在 config/app.php 中手动添加！
```

这样设计的优势：
- **减少配置**：无需手动维护 `config/app.php` 中的服务提供者列表
- **保持一致性**：组件的所有相关配置都在配置器中管理
- **避免遗漏**：不会因为忘记注册服务提供者而导致资源无法发布

### Foundation 组件概要

Foundation 是 Luna Prototype 的基础组件，所有其他组件都依赖于它。它提供了整个框架的核心基础设施。

#### 主要功能

- **Handler（处理器系统）**：统一的处理器注册和执行机制
- **Configuration（配置管理）**：灵活的配置存储和版本控制
- **BusinessEvent（业务事件）**：业务操作的事件定义和格式化
- **Exception（异常处理）**：统一的异常定义和处理机制
- **Installation（安装器）**：模块化的安装流程管理
- **Backupable（备份恢复）**：应用状态的备份和恢复

→ [Foundation 详细文档](src/Foundation/README.md)

#### 核心类

```php
// 处理器管理
$handler = luna_handler();
$handlerInstance = $handler->createHandlerInstance('payment-handler');
$result = $handlerInstance->process($data);

// 配置管理
$config = luna_configuration();
$appGroup = $config->group('app');
$appGroup->set('settings.theme', 'dark');
$appGroup->save();

// 业务事件
$event = luna_business_event();
$message = $event->eventMessage('user.login', ['user' => 'Alice']);
```

Foundation 组件是其他所有组件的基石，提供了标准化的扩展点和基础服务。

## 系统要求

- PHP 8.4+
- Laravel 12.0+
- 支持的数据库: MySQL, PostgreSQL, SQLite

## 安装

通过 Composer 安装：

```bash
composer require dybasedev/luna-prototype
```

## 快速开始

### 1. 基础配置

修改 Laravel 项目中的 `AppServiceProvider` 继承 `LunaServiceProvider`：

```php
// app/Providers/AppServiceProvider.php
<?php

namespace App\Providers;

use Dybasedev\LunaPrototype\Foundation\LunaServiceProvider;

class AppServiceProvider extends LunaServiceProvider
{
    /**
     * 自定义模块注册
     */
    public function customRegister(): void
    {
        // 注册需要的组件
        // $this->registerModule(
        //     LunaAssetsAccountConfigure::create()->build()
        // );
    }

    /**
     * 自定义启动逻辑
     */
    public function customBoot(): void
    {
        // 自定义启动逻辑
    }
}
```

### 2. 发布和运行迁移文件

Luna Prototype 会自动注册组件的服务提供者，无需手动在 `config/app.php` 中添加：

```bash
# 发布组件的迁移文件
php artisan vendor:publish --provider="Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccountServiceProvider"

# 运行迁移
php artisan migrate
```

### 3. 模块注册示例

```php
// app/Providers/AppServiceProvider.php
use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccountConfigure;
use Dybasedev\LunaPrototype\Schedule\LunaScheduleConfigure;

class AppServiceProvider extends LunaServiceProvider
{
    public function customRegister(): void
    {
        // 注册资产账户模块
        $this->registerModule(
            LunaAssetsAccountConfigure::create()->build()
        );
        
        // 注册调度模块
        $this->registerModule(
            LunaScheduleConfigure::create()->build()
        );
        
        // 扩展处理器配置
        $this->extendModule(function() {
            return LunaHandlerConfigure::create()
                ->group('custom_handlers', '自定义处理器', function($register) {
                    $register->handler(CustomHandler::class);
                })
                ->build();
        });
    }
}
```

### 4. 基本使用示例

```php
use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccount;

// 获取资产账户实例
$assetsAccount = app(LunaAssetsAccount::class);

// 创建账户类型
$balanceType = $assetsAccount->createAccountType(
    'balance',
    'default_handler',
    '余额账户',
    '用户主要余额账户'
);
```

## 核心模块

### Foundation 模块

基础架构模块，提供：
- 配置管理系统
- 异常处理机制
- 业务事件定义和格式化系统
- 处理器模式
- 安装器框架
- 备份恢复机制
- 辅助函数

→ [详细文档](src/Foundation/README.md)

### AssetsAccount 模块

资产账户管理模块，提供：
- 多层级账户类型管理
- 账户创建和查询
- 原子性账户操作
- 余额类型管理（可用、冻结、锁定）
- 变更日志记录
- 统计和查询功能

→ [详细文档](src/AssetsAccount/README.md)

### Schedule 模块

任务调度模块，提供：
- 定时任务管理
- 后台作业队列
- 任务状态监控
- 日志记录
- 任务优先级管理
- 失败重试机制

→ [详细文档](src/Schedule/README.md)

### Membership 模块

会员体系模块，提供：
- 会员等级框架
- 里程碑系统（等级系统）
- 会员关系链管理
- 推广代理机制
- 权益管理接口
- 会员数据绑定

→ [详细文档](src/Membership/README.md)

### Showcase 模块

UI组件抽象模块，提供：
- 表单字段组件
- 数据表列组件
- 多前端框架适配
- 动态页面描述
- 后台面板快速扩展
- 前端页面装修

→ [详细文档](src/Showcase/README.md)

### UnitConversion 模块

单位转换模块，提供：
- 单位类别和定义管理
- 灵活的转换处理器（固定汇率、动态汇率、条件汇率）
- 转换上下文和手续费计算
- 与资产账户系统的集成
- 批量转换和缓存优化
- 货币、度量衡等多种单位支持

→ [详细文档](src/UnitConversion/README.md)

### Trade 模块

交易系统模块，提供：
- 完整的交易流程管理
- 可扩展的交易对象（Tradable）接口
- 灵活的支付方式管理
- 交易金额修改器（折扣、税费、运费等）
- 退款和撤销支持
- 交易编号生成器

→ [详细文档](src/Trade/README.md)

### HoldingObject 模块

对象持有系统模块，提供：
- 唯一对象定义和管理
- 灵活的持有状态（正常、禁用、释放等）
- 持有条件验证（数量限制、时间限制等）
- 常见场景支持（每日签到、购买限制、抽奖机会等）
- 并发控制和缓存优化
- 高度抽象的业务组合能力

→ [详细文档](src/HoldingObject/README.md)

### Permission 模块

权限管理模块，提供：
- 基于策略（Policy）的权限定义
- 角色（Role）管理
- 用户组（UserGroup）管理
- 灵活的权限分配和继承
- 权限缓存和性能优化
- 与 Laravel Gate 的集成

→ [详细文档](src/Permission/README.md)

## 架构设计

### 原子化模块设计

Luna Prototype 的模块设计遵循原子化原则，每个模块都是一个完整且独立的功能单元：

```php
// 模块配置示例 - AssetsAccount
LunaAssetsAccountConfigure::create()
    ->model(CustomAssetsAccount::class)           // 自定义模型
    ->serviceProvider(CustomAccountProvider::class) // 自定义服务提供者
    ->build();
```

#### 模块独立性
每个模块都继承自 `LunaModule` 基类，具有完全独立的：
- **配置管理**: 独立的配置存储和版本控制
- **数据库迁移**: 模块专属的数据表结构
- **服务注册**: 独立的服务容器绑定
- **依赖关系**: 明确的模块间依赖声明

#### 组合使用示例
```php
// 电商场景组合
public function customRegister(): void 
{
    // 基础账户系统
    $this->registerModule(LunaAssetsAccountConfigure::create()->build());
    
    // 会员体系
    $this->registerModule(LunaMembershipConfigure::create()->build());
    
    // 扩展支付相关处理器
    $this->extendModule(function() {
        return LunaHandlerConfigure::create()
            ->group('payment', '支付处理器', function($register) {
                $register->handler(AlipayHandler::class);
                $register->handler(WechatPayHandler::class);
                $register->handler(RefundHandler::class);
            })
            ->build();
    });
}

// 游戏场景组合  
public function customRegister(): void 
{
    // 只需要积分和道具系统
    $this->registerModule(LunaAssetsAccountConfigure::create()->build());
    
    // 游戏专用处理器
    $this->extendModule(function() {
        return LunaHandlerConfigure::create()
            ->group('game', '游戏处理器', function($register) {
                $register->handler(ExperienceHandler::class);
                $register->handler(ItemHandler::class);
                $register->handler(AchievementHandler::class);
            })
            ->build();
    });
}
```

### 处理器模式

通过继承 `LunaServiceProvider` 可以方便地注册和扩展处理器：

```php
// 在 AppServiceProvider 中注册处理器
public function customRegister(): void
{
    $this->extendModule(function() {
        return LunaHandlerConfigure::create()
            ->group('payment_handlers', '支付处理器', function($register) {
                $register->handler(AlipayHandler::class);
                $register->handler(WechatHandler::class);
            })
            ->build();
    });
}

// 使用处理器
$handler = app(LunaHandler::class);
$handler->execute('payment_handler', $data);
```

### 业务事件系统

BusinessEvent模块用于管理系统中各种业务操作的事件定义和描述格式化，主要用于账户流水、操作日志等场景中记录触发操作的事件信息，并将event_id和payload转换为用户可见的描述文本：

```php
// 创建业务事件定义
$businessEvent = app(LunaBusinessEvent::class);
$businessEvent->createBusinessEvent(
    'transfer_money',           // 事件名称
    'account_operations',       // 事件分组
    'transfer_handler',         // 处理器
    '转账操作：从 {from_account} 转出 {amount} 到 {to_account}', // 格式化模板
    '转账操作'                  // 显示名称
);

// 格式化事件消息（用于显示给用户）
$message = $businessEvent->eventMessage('transfer_money', [
    'from_account' => '张三的余额账户',
    'to_account' => '李四的余额账户', 
    'amount' => '100.00'
]);
// 输出: "转账操作：从 张三的余额账户 转出 100.00 到 李四的余额账户"

// 在账户操作中使用事件ID记录
$operation = luna_account_transfer()
    ->from($user1, 'balance')
    ->to($user2, 'balance') 
    ->event('transfer_money')  // 关联业务事件
    ->amount(100);
```

### 单位转换系统

UnitConversion 模块提供了灵活的单位转换功能，特别适合多币种、国际化等场景：

```php
use Dybasedev\LunaPrototype\UnitConversion\LunaUnitConversion;
use Dybasedev\LunaPrototype\UnitConversion\Attributes\UnitAttributes;

// 获取单位转换实例
$unitConversion = app(LunaUnitConversion::class);

// 创建货币单位（使用参数类）
$unitConversion->createUnit('currency', 'USD', 
    UnitAttributes::create()
        ->symbol('$')
        ->displayName('美元')
        ->precision(2)
        ->asBase()  // 设为基准单位
);

$unitConversion->createUnit('currency', 'CNY',
    UnitAttributes::create()
        ->symbol('¥')
        ->displayName('人民币')
        ->baseValue(7.0)  // 相对于基准单位的汇率
);

// 简单转换
$result = $unitConversion->convert('USD', 'CNY', 100);
echo $result->getToAmount(); // 700.0

// 带上下文的转换（如手续费计算）
use Dybasedev\LunaPrototype\UnitConversion\Conversion\ConversionContext;

$context = ConversionContext::make([
    'calculate_fee' => true,
    'parameters' => [
        'user_level' => 'vip',
        'amount_tier' => 'large'
    ]
]);

$result = $unitConversion->convert('USD', 'CNY', 1000, $context);
echo $result->getToAmount();  // 转换后金额
echo $result->getFee();       // 手续费

// 与资产账户集成
use Dybasedev\LunaPrototype\UnitConversion\Integration\AssetsAccount\AssetsAccountIntegration;

$integration = new AssetsAccountIntegration($unitConversion);

// 为账户类型添加货币支持
$integration->addCurrencySupport($accountType, 'USD');

// 转换账户余额
$cnyBalance = $integration->convertBalance($account, 'CNY');
echo $cnyBalance; // 转换后的余额

// 批量转换
$conversions = [
    'usd_to_cny' => ['from' => 'USD', 'to' => 'CNY', 'amount' => 100],
    'usd_to_eur' => ['from' => 'USD', 'to' => 'EUR', 'amount' => 100],
];
$results = $unitConversion->batchConvert($conversions);
```

### 交易系统

Trade 模块提供了完整的交易流程管理，适用于电商、充值、服务购买等场景：

```php
use Dybasedev\LunaPrototype\Trade\LunaTrade;
use Dybasedev\LunaPrototype\Trade\Payment\PaymentConfiguration;

// 配置支付方式
$paymentConfig = PaymentConfiguration::create()
    ->registerMethod('alipay', AlipayPayment::class, [
        'app_id' => 'your_app_id',
        'private_key' => 'your_private_key',
    ])
    ->registerMethod('wechat', WechatPayment::class, [
        'mch_id' => 'your_mch_id',
        'api_key' => 'your_api_key',
    ])
    ->setDefaultMethod('alipay')
    ->build();

// 创建交易
$trade = app(LunaTrade::class);
$transaction = $trade->createTransaction($buyer, $product, 100.00);

// 应用金额修改器（折扣、税费等）
$transaction->applyModifier(new DiscountModifier(0.1));  // 10% 折扣
$transaction->applyModifier(new TaxModifier(0.06));      // 6% 税费

// 发起支付
$paymentResult = $trade->pay($transaction, 'alipay', [
    'return_url' => 'https://example.com/return',
    'notify_url' => 'https://example.com/notify',
]);

// 处理支付结果
if ($paymentResult->isSuccess()) {
    // 支付成功
    $trade->completeTransaction($transaction);
} elseif ($paymentResult->isPending()) {
    // 等待支付回调
    echo $paymentResult->getRedirectUrl();
}
```

### 对象持有系统

HoldingObject 模块提供了灵活的对象持有关系管理，适用于各种业务场景：

```php
use Dybasedev\LunaPrototype\HoldingObject\LunaHoldingObject;
use Dybasedev\LunaPrototype\HoldingObject\LunaHoldingObjectConfigure;

// 配置持有对象
$configure = LunaHoldingObjectConfigure::create()
    ->registerUniqueObject('daily-checkin', DailyCheckInObject::class)
    ->registerUniqueObject('product-limit', ProductPurchaseLimitObject::class)
    ->registerUniqueObject('lottery-chance', LotteryChanceObject::class)
    ->build();

$holdingObject = new LunaHoldingObject($configure);

// 每日签到
$checkIn = $holdingObject->createUniqueHolding(
    $user,
    'daily-checkin',
    1,  // 持有数量
    ['check_in_date' => date('Y-m-d')]
);

if ($checkIn->isSuccessful()) {
    echo "签到成功！";
} else {
    echo "今日已签到";
}

// 购买限制检查
$canPurchase = $holdingObject->checkHoldingLimit(
    $user,
    'product-limit',
    $product->id,
    $quantity
);

if (!$canPurchase) {
    echo "超出购买限制";
}

// 抽奖机会管理
$holdingObject->updateHolding(
    $user,
    'lottery-chance',
    'activity_001',
    5  // 增加5次抽奖机会
);
```

### 权限系统

Permission 模块提供了基于策略的灵活权限管理：

```php
use Dybasedev\LunaPrototype\Permission\LunaPermission;

$permission = app(LunaPermission::class);

// 创建策略
$policy = $permission->createPolicy('article.manage', [
    [
        'effect' => 'allow',
        'actions' => ['create', 'update'],
        'resources' => ['article:*'],
    ],
    [
        'effect' => 'deny',
        'actions' => ['delete'],
        'resources' => ['article:protected:*'],
    ]
]);

// 创建角色并分配策略
$role = $permission->createRole('editor', '编辑员');
$permission->assignPolicy($policy, $role);

// 分配角色给用户
$permission->assignPolicy($role->name, $user);

// 权限检查
if ($user->hasPermission('article.manage', 'create', 'article:123')) {
    // 允许创建文章
}

// 用户组管理
$group = $permission->createUserGroup('vip-users', 'VIP用户组');
$group->addMember($user);
$permission->assignPolicy($policy, $group);
```

## 测试

项目使用 Pest 3.0 作为测试框架。

### 测试环境配置

在运行测试前，需要配置测试环境：

#### 交互式配置（推荐）
```bash
# 运行配置脚本，按提示输入数据库配置
php scripts/setup-testing-env.php
```

#### 快速配置（CI/自动化）
```bash
# 使用默认配置
php scripts/setup-testing-env-ci.php

# 自定义数据库配置
php scripts/setup-testing-env-ci.php \
  --db-host=localhost \
  --db-username=test_user \
  --db-password=secret \
  --db-database=luna_test
```

#### 手动配置
如果您偏好手动配置，可创建 `.env.testing` 文件：

```bash
# 复制示例并修改
cp .env.testing.example .env.testing
# 然后编辑 .env.testing 文件
```

### 运行测试

确保测试数据库存在并可访问，然后运行测试：

```bash
# 运行所有测试
./vendor/bin/pest

# 运行特定模块测试
./vendor/bin/pest tests/Unit/Foundation/
./vendor/bin/pest tests/Unit/AssetsAccount/

# 运行测试并生成覆盖率报告
./vendor/bin/pest --coverage
```

### CI 集成示例

GitHub Actions 配置示例：

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.4
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: luna_prototype_test
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.4
          
      - name: Install dependencies
        run: composer install --no-progress --prefer-dist --optimize-autoloader
        
      - name: Setup testing environment
        run: php scripts/setup-testing-env-ci.php --db-password=root
        
      - name: Run tests
        run: ./vendor/bin/pest
```

## 扩展开发指南

### 创建自定义模块

```php
// 1. 创建模块配置
class CustomModuleConfigure extends LunaModuleConfigure 
{
    public function name(): string 
    {
        return 'custom_module';
    }
    
    public function dependencies(): array 
    {
        return ['luna_foundation']; // 声明依赖
    }
}

// 2. 在 AppServiceProvider 中注册
public function customRegister(): void 
{
    $this->registerModule(CustomModuleConfigure::create()->build());
}
```

### 扩展现有功能

```php
// 扩展账户类型
$this->extendModule(function() {
    return LunaAssetsAccountConfigure::create()
        ->accountType('loyalty_points', PointsHandler::class, '积分账户')
        ->accountType('gift_cards', GiftCardHandler::class, '礼品卡')
        ->build();
});

// 扩展业务事件
$this->extendModule(function() {
    return LunaBusinessEventConfigure::create()
        ->group('marketing', '营销事件', function($register) {
            $register->event('coupon_used', CouponEventHandler::class);
            $register->event('promotion_applied', PromotionEventHandler::class);
        })
        ->build();
});
```

### 最佳实践

1. **保持原子性**: 每个模块只负责一个特定的业务领域
2. **明确依赖**: 通过 `dependencies()` 方法明确声明模块依赖
3. **扩展优先**: 优先通过处理器、配置等机制扩展，避免修改核心代码
4. **向后兼容**: 保持 API 的向后兼容性，使用新方法而非修改现有方法签名

## 贡献

欢迎提交 Pull Request 和 Issue。请确保：

1. 遵循原子化组件设计原则
2. 代码符合 PSR-12 编码标准
3. 添加适当的测试用例
4. 更新相关文档
5. 提交前运行所有测试

## 许可证

本项目采用 Apache License 2.0 许可证。详见 [LICENSE](LICENSE) 文件。

## 支持

如有问题或建议，请提交 Issue 或联系开发团队。

---

**Luna Prototype Team**  
基于 Laravel 的快速业务原型开发框架
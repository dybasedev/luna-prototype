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

## 核心特性

- **原子化模块架构**: 每个模块都是独立的原子化组件，可按需选择和组合
- **资产账户系统**: 提供多层级账户类型、余额管理、原子化操作等基础功能
- **调度任务系统**: 灵活的定时任务和后台作业管理基础框架
- **会员体系框架**: 可扩展的会员等级和权益管理基础组件
- **UI组件抽象层**: 前端无关的表单字段和数据展示组件抽象
- **单位转换系统**: 支持多种单位类型转换，包括货币、长度、重量等，支持动态汇率
- **配置管理系统**: 支持版本控制的灵活配置存储和管理机制
- **处理器扩展模式**: 基于处理器的业务逻辑扩展点，支持插件化开发
- **业务事件系统**: 业务操作事件定义和用户友好的描述格式化机制

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
        // 注册自定义模块
        // $this->registerModule(CustomModuleConfigure::create()->build());
        
        // 扩展现有模块配置
        // $this->extendModule(function() {
        //     return CustomModuleConfigure::create()
        //         ->extend('existing_module')
        //         ->build();
        // });
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

### 2. 运行数据库迁移

```bash
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
- 辅助函数

### AssetsAccount 模块

资产账户管理模块，提供：
- 多层级账户类型管理
- 账户创建和查询
- 原子性账户操作
- 余额类型管理（可用、冻结、锁定）

### Schedule 模块

任务调度模块，提供：
- 定时任务管理
- 后台作业队列
- 任务状态监控
- 日志记录

### Membership 模块

会员体系模块，提供：
- 会员等级框架
- 权益管理接口
- 会员数据绑定

### Showcase 模块

UI组件抽象模块，提供：
- 表单字段组件
- 数据表列组件
- 多前端框架适配

### UnitConversion 模块

单位转换模块，提供：
- 单位类别和定义管理
- 灵活的转换处理器（固定汇率、动态汇率、条件汇率）
- 转换上下文和手续费计算
- 与资产账户系统的集成
- 批量转换和缓存优化

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
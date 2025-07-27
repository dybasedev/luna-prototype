# Luna Prototype 示例代码

本目录包含所有 Luna Prototype 组件的完整示例代码。每个子目录对应一个具体的组件，包含实际使用示例。

## 目录结构

```
examples/
├── holding-object/     # HoldingObject 组件示例
├── membership/         # 会员体系示例  
├── showcase/           # Showcase UI 抽象层示例
└── trade/              # 交易系统示例
```

## 组件示例

### HoldingObject（对象持有系统）
- **SimpleExample.php** - 基础使用模式，包括签到、限购、抽奖次数等
- **DailyCheckInObject.php** - 每日签到实现
- **FeatureAccessObject.php** - 功能访问控制
- **LotteryChanceObject.php** - 抽奖次数管理
- **ProductPurchaseLimitObject.php** - 商品限购
- **ParamsBuilderExample.php** - 参数构建器使用
- **ParamsUsageExample.php** - 参数使用模式

### Membership（会员体系）
- **CompleteRelationshipExample.php** - 完整的会员关系示例
- **MembershipBindingExample.php** - 会员绑定模式
- **SessionHolderExample.php** - SessionHolder 使用

### Showcase（UI组件抽象）
- **UserDataTable.php** - 用户管理数据表格
- **LogDataTable.php** - 日志查看数据表格
- **AttributeExampleDataTable.php** - 使用 PHP 属性
- **RemoteSchemaExample.php** - 动态表单结构
- **ShowcaseServiceProvider.php** - 服务提供者配置
- **ShowcaseUsageExample.php** - 通用使用模式

### Trade（交易系统）
- **MockThirdPartyPayment.php** - 模拟第三方支付
- **CustomTransactionNumberGenerator.php** - 自定义交易号生成
- **AmountModifiers/** - 金额修改器示例
  - CouponModifier.php - 优惠券
  - DiscountModifier.php - 折扣
  - ShippingFeeModifier.php - 运费
  - TaxModifier.php - 税费

## 使用方法

所有示例都设计为独立可用，展示实际使用模式。使用这些示例：

1. 复制相关示例文件到您的项目
2. 更新命名空间以匹配您的项目结构
3. 根据您的具体需求修改实现

## 自动加载

这些示例使用 `Examples\` 命名空间前缀。如果您想直接运行它们，请在 `composer.json` 中添加：

```json
{
    "autoload": {
        "psr-4": {
            "Examples\\": "examples/"
        }
    }
}
```

然后运行 `composer dump-autoload` 重新生成自动加载器。
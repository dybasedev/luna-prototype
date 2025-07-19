# UnitConversion 集成套件

本目录包含 UnitConversion 组件与其他组件的集成实现。每个集成都在独立的子目录中，以便于维护和扩展。

## 目录结构

```
Integration/
├── README.md                   # 本文件
├── helpers.php                 # 通用辅助函数
└── AssetsAccount/              # 与 AssetsAccount 组件的集成
    ├── README.md               # AssetsAccount 集成详细文档
    ├── ConversionAwareAccountOperations.php
    ├── ConversionAwareOperationBuilder.php
    ├── UnitConversionTransferBuilder.php
    └── AssetsAccountIntegration.php
```

## 可用集成

### 1. AssetsAccount 集成

提供多币种账户管理、跨币种转账、自动汇率转换等功能。

- **命名空间**: `Dybasedev\LunaPrototype\UnitConversion\Integration\AssetsAccount`
- **文档**: [AssetsAccount/README.md](AssetsAccount/README.md)
- **主要功能**:
  - 多币种账户转账
  - 自动汇率转换
  - 手续费计算
  - 转换上下文记录

## 设计原则

1. **独立性**: 每个集成都在独立的子目录中，有自己的命名空间
2. **可选性**: 集成是可选的，不影响核心组件的使用
3. **扩展性**: 易于添加新的集成而不影响现有代码
4. **兼容性**: 保持与被集成组件的向后兼容

## 添加新集成

如需为 UnitConversion 添加与其他组件的集成：

1. 在 `Integration/` 下创建新的子目录，如 `Integration/NewComponent/`
2. 使用独立的命名空间，如 `Dybasedev\LunaPrototype\UnitConversion\Integration\NewComponent`
3. 提供详细的 README.md 文档
4. 如需要辅助函数，创建独立的 helpers.php 文件
5. 在 composer.json 中添加新的 autoload 文件路径（如果有 helpers.php）

## 使用说明

集成功能通常需要：

1. 确保相关组件已安装和配置
2. 使用集成提供的配置方法启用功能
3. 参考各集成的文档了解具体用法

示例：
```php
// 使用 AssetsAccount 集成
use Dybasedev\LunaPrototype\UnitConversion\Integration\AssetsAccount\ConversionAwareAccountOperations;

$operation = luna_conversion_aware_operations();
// ... 使用集成功能
```
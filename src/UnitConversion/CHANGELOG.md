# UnitConversion 组件更新日志

## 改进内容

### 1. 参数类支持
- 创建了 `CategoryAttributes` 和 `UnitAttributes` 参数类
- 支持链式调用，提供更好的 IDE 支持和文档说明
- `createCategory` 和 `createUnit` 方法现在同时支持数组和参数类

使用示例：
```php
// 使用参数类创建类别
$category = $unitConversion->createCategory('test', 
    CategoryAttributes::create()
        ->description('测试类别')
        ->active()
        ->config(['key' => 'value'])
);

// 使用参数类创建单位
$unit = $unitConversion->createUnit('currency', 'USD', 
    UnitAttributes::create()
        ->symbol('$')
        ->displayName('美元')
        ->precision(2)
        ->asBase()
);
```

### 2. ID 生成逻辑修复
- `UnitDefinition` 模型现在会自动生成 ID（使用 hash_code）
- 添加了 `generateId` 静态方法
- 在模型创建时自动设置 ID

### 3. 缓存机制优化
- 为转换规则查询添加了缓存
- 新增 `getAllCategories()` 方法，使用永久缓存
- 新增 `getUnits()` 方法，支持批量获取单位
- 新增 `clearAllCache()` 方法，清除所有相关缓存
- 优化了缓存键命名规范

### 4. 新增功能
- 批量获取单位定义
- 获取所有类别（永久缓存）
- 清除所有缓存的方法

### 5. 测试覆盖
- 添加了参数类使用的测试
- 添加了 ID 自动生成的测试
- 添加了缓存机制的测试
- 添加了批量获取功能的测试

## 使用建议

1. 对于复杂的属性配置，推荐使用参数类，能获得更好的 IDE 支持
2. 对于频繁访问的数据（如类别列表），使用 `getAllCategories()` 方法可以获得更好的性能
3. 在需要批量操作时，使用 `getUnits()` 方法比单独获取更高效
4. 在数据更新后，如果需要立即生效，可以调用 `clearAllCache()` 清除缓存

## 注意事项

1. 由于使用了缓存标签功能的限制，单位和规则的缓存无法通过 `clearAllCache()` 完全清除
2. 建议在生产环境中配置合适的缓存时长（通过 `defaultCacheDuration` 配置）
3. 永久缓存的数据（如 `getAllCategories()`）需要手动清除缓存才能获取最新数据
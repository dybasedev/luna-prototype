<?php

namespace Dybasedev\LunaPrototype\UnitConversion;

use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandlerConfigure;
use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;
use Dybasedev\LunaPrototype\UnitConversion\Handlers\ConditionalRateHandler;
use Dybasedev\LunaPrototype\UnitConversion\Handlers\DynamicRateHandler;
use Dybasedev\LunaPrototype\UnitConversion\Handlers\FixedRateHandler;
use Dybasedev\LunaPrototype\UnitConversion\Models\UnitCategory;
use Dybasedev\LunaPrototype\UnitConversion\Models\UnitConversionRule;
use Dybasedev\LunaPrototype\UnitConversion\Models\UnitDefinition;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;

/**
 * 单位转换模块配置类
 */
class LunaUnitConversionConfigure extends LunaModuleConfigure
{
    /**
     * 单位类别模型类名
     */
    public string $unitCategoryModel = UnitCategory::class;

    /**
     * 单位定义模型类名
     */
    public string $unitDefinitionModel = UnitDefinition::class;

    /**
     * 转换规则模型类名
     */
    public string $conversionRuleModel = UnitConversionRule::class;

    /**
     * 是否启用转换事件
     */
    public bool $enableEvents = true;

    /**
     * 默认缓存时间（秒）
     */
    public int $defaultCacheDuration = 3600;

    /**
     * 预定义的单位类别
     */
    protected array $predefinedCategories = [
        'currency' => ['display_name' => '货币', 'description' => '各国货币单位'],
        'length' => ['display_name' => '长度', 'description' => '长度度量单位'],
        'weight' => ['display_name' => '重量', 'description' => '重量度量单位'],
        'volume' => ['display_name' => '体积', 'description' => '体积度量单位'],
        'area' => ['display_name' => '面积', 'description' => '面积度量单位'],
        'time' => ['display_name' => '时间', 'description' => '时间度量单位'],
        'temperature' => ['display_name' => '温度', 'description' => '温度度量单位'],
        'digital' => ['display_name' => '数字存储', 'description' => '数字存储单位'],
        'energy' => ['display_name' => '能量', 'description' => '能量度量单位'],
        'custom' => ['display_name' => '自定义', 'description' => '自定义业务单位'],
    ];

    /**
     * 获取组件名称
     */
    public function name(): string
    {
        return 'luna.unit-conversion';
    }

    /**
     * 获取服务提供者类名
     */
    public function serviceProvider(): ?string
    {
        return LunaUnitConversionServiceProvider::class;
    }

    /**
     * 替换单位类别模型
     */
    public function useUnitCategoryModel(string $model): static
    {
        $this->unitCategoryModel = $model;
        return $this;
    }

    /**
     * 替换单位定义模型
     */
    public function useUnitDefinitionModel(string $model): static
    {
        $this->unitDefinitionModel = $model;
        return $this;
    }

    /**
     * 替换转换规则模型
     */
    public function useConversionRuleModel(string $model): static
    {
        $this->conversionRuleModel = $model;
        return $this;
    }

    /**
     * 设置是否启用事件
     */
    public function enableEvents(bool $enable = true): static
    {
        $this->enableEvents = $enable;
        return $this;
    }

    /**
     * 设置默认缓存时间
     */
    public function setDefaultCacheDuration(int $seconds): static
    {
        $this->defaultCacheDuration = $seconds;
        return $this;
    }

    /**
     * 添加预定义类别
     */
    public function addPredefinedCategory(string $name, array $attributes): static
    {
        $this->predefinedCategories[$name] = $attributes;
        return $this;
    }

    /**
     * 获取预定义类别
     */
    public function getPredefinedCategories(): array
    {
        return $this->predefinedCategories;
    }

    /**
     * 注册到容器
     */
    public function register(Container $container): void
    {
        // 注册组件到容器
        $container->singleton('luna.unit-conversion', function ($app) {
            return new LunaUnitConversion(
                $this,
                $app->make('cache.store'),
                $app->make(LunaHandler::class)
            );
        });
        
        $container->alias('luna.unit-conversion', LunaUnitConversion::class);
    }

    /**
     * @throws BindingResolutionException
     */
    public function boot(Container $container): void
    {
        // 注册处理器组
        $container->make(LunaHandlerConfigure::class)->group('unit-conversion', '单位转换处理器', function ($register) {
            $register->handler(ConditionalRateHandler::class);
            $register->handler(DynamicRateHandler::class);
            $register->handler(FixedRateHandler::class);
        });
    }


}

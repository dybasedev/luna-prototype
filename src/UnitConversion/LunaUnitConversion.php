<?php

namespace Dybasedev\LunaPrototype\UnitConversion;

use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\LunaModule;
use Dybasedev\LunaPrototype\UnitConversion\Conversion\ConversionContext;
use Dybasedev\LunaPrototype\UnitConversion\Conversion\ConversionResult;
use Dybasedev\LunaPrototype\UnitConversion\Handlers\UnitConversionHandler;
use Dybasedev\LunaPrototype\UnitConversion\Models\UnitCategory;
use Dybasedev\LunaPrototype\UnitConversion\Models\UnitConversionLog;
use Dybasedev\LunaPrototype\UnitConversion\Models\UnitConversionRule;
use Dybasedev\LunaPrototype\UnitConversion\Models\UnitDefinition;
use Dybasedev\LunaPrototype\UnitConversion\Attributes\CategoryAttributes;
use Dybasedev\LunaPrototype\UnitConversion\Attributes\UnitAttributes;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 单位转换模块主类
 * 
 * 提供单位管理、转换等功能
 */
class LunaUnitConversion extends LunaModule
{
    /**
     * 缓存键前缀
     */
    const string CACHE_PREFIX = 'luna_unit_conversion:';
    
    /**
     * 构造函数
     */
    public function __construct(
        protected LunaUnitConversionConfigure $configure,
        protected Cache $cache,
        protected LunaHandler $handler
    ) {}
    
    /**
     * 创建或更新单位类别
     * 
     * @param string $name 类别名称
     * @param CategoryAttributes|array $attributes 类别属性
     */
    public function createCategory(string $name, CategoryAttributes|array $attributes = []): UnitCategory
    {
        $categoryModel = $this->configure->unitCategoryModel;
        
        // 转换参数类为数组
        if ($attributes instanceof CategoryAttributes) {
            $attributes = $attributes->toArray();
        }
        
        return $categoryModel::findOrCreateByName($name, array_merge([
            'is_active' => true,
        ], $attributes));
    }
    
    /**
     * 获取单位类别
     */
    public function getCategory(string $name): ?UnitCategory
    {
        $cacheKey = self::CACHE_PREFIX . 'category:' . $name;
        
        return $this->cache->remember($cacheKey, $this->configure->defaultCacheDuration, function () use ($name) {
            $categoryModel = $this->configure->unitCategoryModel;
            return $categoryModel::findByName($name);
        });
    }
    
    /**
     * 获取所有活跃的单位类别
     */
    public function getActiveCategories(): Collection
    {
        $cacheKey = self::CACHE_PREFIX . 'categories:active';
        
        return $this->cache->remember($cacheKey, $this->configure->defaultCacheDuration, function () {
            $categoryModel = $this->configure->unitCategoryModel;
            return $categoryModel::where('is_active', true)->get();
        });
    }
    
    /**
     * 获取所有单位类别（使用永久缓存）
     */
    public function getAllCategories(): Collection
    {
        $cacheKey = self::CACHE_PREFIX . 'categories:all';
        
        return collect($this->cache->rememberForever($cacheKey, function () {
            $categoryModel = $this->configure->unitCategoryModel;
            return $categoryModel::with('units')->get()->all();
        }));
    }
    
    /**
     * 批量获取单位定义
     * 
     * @param array $codes 单位代码数组
     * @param string|null $categoryName 类别名称（可选）
     */
    public function getUnits(array $codes, ?string $categoryName = null): Collection
    {
        if (empty($codes)) {
            return collect();
        }
        
        $category = null;
        if ($categoryName) {
            $category = $this->getCategory($categoryName);
            if (!$category) {
                return collect();
            }
        }
        
        // 先尝试从缓存获取
        $units = collect();
        $missingCodes = [];
        
        foreach ($codes as $code) {
            $unit = $this->getUnit($code, $categoryName);
            if ($unit) {
                $units->push($unit);
            } else {
                $missingCodes[] = $code;
            }
        }
        
        // 从数据库获取缺失的单位
        if (!empty($missingCodes)) {
            $unitModel = $this->configure->unitDefinitionModel;
            $query = $unitModel::whereIn('code', $missingCodes);
            
            if ($category) {
                $query->where('category_id', $category->id);
            }
            
            $dbUnits = $query->get();
            
            // 缓存新获取的单位
            foreach ($dbUnits as $unit) {
                $cacheKey = self::CACHE_PREFIX . 'unit:' . $unit->code . ':' . ($unit->category_id ?? 'any');
                $this->cache->put($cacheKey, $unit, $this->configure->defaultCacheDuration);
                $units->push($unit);
            }
        }
        
        return $units;
    }
    
    /**
     * 创建或更新单位定义
     * 
     * @param string $categoryName 类别名称
     * @param string $code 单位代码
     * @param UnitAttributes|array $attributes 单位属性
     */
    public function createUnit(string $categoryName, string $code, UnitAttributes|array $attributes = []): UnitDefinition
    {
        $category = $this->getCategory($categoryName);
        if (!$category) {
            $category = $this->createCategory($categoryName);
        }
        
        $unitModel = $this->configure->unitDefinitionModel;
        
        // 转换参数类为数组
        if ($attributes instanceof UnitAttributes) {
            $attributes = $attributes->toArray();
        }
        
        // 如果是该类别的第一个单位且未指定基准值，则设为基准单位
        if (!isset($attributes['base_value']) && $category->units()->count() === 0) {
            $attributes['base_value'] = 1.0;
            $attributes['is_base'] = true;
        }
        
        // 生成单位ID
        $unitId = hash_code($category->name . ':' . $code);
        
        // 查找现有单位
        $unit = $unitModel::where('id', $unitId)->first();
        
        if (!$unit) {
            // 创建新单位
            $unit = new $unitModel(array_merge([
                'id' => $unitId,
                'category_id' => $category->id,
                'code' => $code,
                'display_name' => $code,
                'precision' => 2,
                'base_value' => 1.0,
                'is_base' => false,
                'is_active' => true,
            ], $attributes));
            $unit->save();
        } else {
            // 更新现有单位
            $unit->update($attributes);
        }
        
        // 清除相关缓存
        $this->clearUnitCache($code, $category->id);
        
        return $unit;
    }
    
    /**
     * 获取单位定义
     */
    public function getUnit(string $code, ?string $categoryName = null): ?UnitDefinition
    {
        $categoryId = null;
        if ($categoryName) {
            $category = $this->getCategory($categoryName);
            $categoryId = $category?->id;
        }
        
        $cacheKey = self::CACHE_PREFIX . 'unit:' . $code . ':' . ($categoryId ?? 'any');
        
        return $this->cache->remember($cacheKey, $this->configure->defaultCacheDuration, function () use ($code, $categoryId) {
            $unitModel = $this->configure->unitDefinitionModel;
            return $unitModel::findByCode($code, $categoryId);
        });
    }
    
    /**
     * 单位转换
     */
    public function convert(
        string|UnitDefinition $from,
        string|UnitDefinition $to,
        float $amount,
        ?ConversionContext $context = null
    ): ConversionResult {
        // 获取单位对象
        $fromUnit = $from instanceof UnitDefinition ? $from : $this->getUnit($from);
        $toUnit = $to instanceof UnitDefinition ? $to : $this->getUnit($to);
        
        if (!$fromUnit || !$toUnit) {
            throw new \InvalidArgumentException('Invalid unit code provided');
        }
        
        // 创建默认上下文
        if (!$context) {
            $context = new ConversionContext();
        }
        
        // 查找适用的转换处理器
        $handler = $this->findConversionHandler($fromUnit, $toUnit);
        
        // 执行转换
        $result = $handler->convert($fromUnit, $toUnit, $amount, $context);
        
        // 触发转换完成事件
        if ($this->configure->enableEvents) {
            event(new \Dybasedev\LunaPrototype\UnitConversion\Events\ConversionCompleted(
                $fromUnit,
                $toUnit,
                $amount,
                $result,
                $context
            ));
        }
        
        return $result;
    }
    
    /**
     * 批量转换
     */
    public function batchConvert(
        array $conversions,
        ?ConversionContext $context = null
    ): array {
        $results = [];
        
        foreach ($conversions as $key => $conversion) {
            try {
                $results[$key] = $this->convert(
                    $conversion['from'],
                    $conversion['to'],
                    $conversion['amount'],
                    $context
                );
            } catch (\Exception $e) {
                $results[$key] = [
                    'error' => true,
                    'message' => $e->getMessage(),
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * 获取转换率
     */
    public function getRate(
        string|UnitDefinition $from,
        string|UnitDefinition $to,
        ?ConversionContext $context = null
    ): float {
        $fromUnit = $from instanceof UnitDefinition ? $from : $this->getUnit($from);
        $toUnit = $to instanceof UnitDefinition ? $to : $this->getUnit($to);
        
        if (!$fromUnit || !$toUnit) {
            throw new \InvalidArgumentException('Invalid unit code provided');
        }
        
        if (!$context) {
            $context = new ConversionContext();
        }
        
        $handler = $this->findConversionHandler($fromUnit, $toUnit);
        
        return $handler->getRate($fromUnit, $toUnit, $context);
    }
    
    /**
     * 创建转换规则
     */
    public function createConversionRule(
        string|UnitDefinition $from,
        string|UnitDefinition $to,
        string $handlerClass,
        array $config = [],
        int $priority = 0
    ): UnitConversionRule {
        $fromUnit = $from instanceof UnitDefinition ? $from : $this->getUnit($from);
        $toUnit = $to instanceof UnitDefinition ? $to : $this->getUnit($to);
        
        if (!$fromUnit || !$toUnit) {
            throw new \InvalidArgumentException('Invalid unit code provided');
        }
        
        // 查找处理器实体
        $handlerModel = $this->handler->getAllEntityHandlers()
            ->where('handler', $handlerClass)
            ->where('group_id', hash_code('unit-conversions'))
            ->first();
            
        if (!$handlerModel) {
            throw new \RuntimeException("Handler {$handlerClass} not found in unit-conversions group");
        }
        
        $ruleModel = $this->configure->conversionRuleModel;
        
        $rule = $ruleModel::create([
            'from_unit_id' => $fromUnit->id,
            'to_unit_id' => $toUnit->id,
            'handler_id' => $handlerModel->id,
            'config' => $config,
            'priority' => $priority,
            'is_active' => true,
        ]);
        
        // 清除相关缓存
        $this->clearRuleCache($fromUnit->id, $toUnit->id);
        
        return $rule;
    }
    
    /**
     * 查找适用的转换处理器
     */
    protected function findConversionHandler(UnitDefinition $fromUnit, UnitDefinition $toUnit): UnitConversionHandler
    {
        $cacheKey = self::CACHE_PREFIX . 'rule:' . $fromUnit->id . ':' . $toUnit->id;
        
        // 缓存转换规则查询结果
        $rule = $this->cache->remember($cacheKey, $this->configure->defaultCacheDuration, function () use ($fromUnit, $toUnit) {
            $ruleModel = $this->configure->conversionRuleModel;
            return $ruleModel::with('handler')
                ->where('from_unit_id', $fromUnit->id)
                ->where('to_unit_id', $toUnit->id)
                ->where('is_active', true)
                ->orderByDesc('priority')
                ->first();
        });
        
        if ($rule && $rule->handler) {
            $handlerClass = $rule->handler->handler ?: $rule->handler->name;
            $handler = app()->make($handlerClass);
            if ($handler instanceof UnitConversionHandler) {
                // 应用规则配置
                if (!empty($rule->config)) {
                    $handler->withConfig($rule->config);
                }
                return $handler;
            }
        }
        
        // 使用默认处理器
        $defaultHandlerClass = $this->getDefaultHandlerClass($fromUnit, $toUnit);
        $handler = new $defaultHandlerClass();
        
        if (!$handler instanceof UnitConversionHandler) {
            throw new \RuntimeException('Invalid conversion handler');
        }
        
        return $handler;
    }
    
    /**
     * 获取默认处理器类
     */
    protected function getDefaultHandlerClass(UnitDefinition $fromUnit, UnitDefinition $toUnit): string
    {
        // 同类别单位使用固定比例处理器
        if ($fromUnit->category_id === $toUnit->category_id) {
            return \Dybasedev\LunaPrototype\UnitConversion\Handlers\FixedRateHandler::class;
        }
        
        // 跨类别默认使用条件化处理器
        return \Dybasedev\LunaPrototype\UnitConversion\Handlers\ConditionalRateHandler::class;
    }
    
    
    /**
     * 初始化预定义类别和单位
     */
    public function initializePredefinedData(): void
    {
        DB::transaction(function () {
            // 创建预定义类别
            foreach ($this->configure->getPredefinedCategories() as $name => $attributes) {
                $this->createCategory($name, $attributes);
            }
            
            // 创建一些常用单位
            $this->createCommonUnits();
        });
    }
    
    /**
     * 创建常用单位
     */
    protected function createCommonUnits(): void
    {
        // 货币单位 - 使用参数类示例
        $this->createUnit('currency', 'USD', 
            UnitAttributes::create()
                ->symbol('$')
                ->displayName('美元')
                ->asBase()
        );
        
        $this->createUnit('currency', 'CNY', 
            UnitAttributes::create()
                ->symbol('¥')
                ->displayName('人民币')
                ->baseValue(7.0) // 示例汇率
        );
        
        $this->createUnit('currency', 'EUR', 
            UnitAttributes::create()
                ->symbol('€')
                ->displayName('欧元')
                ->baseValue(0.85)
        );
        
        // 长度单位
        $this->createUnit('length', 'm', [
            'symbol' => 'm',
            'display_name' => '米',
            'base_value' => 1.0,
            'is_base' => true,
        ]);
        
        $this->createUnit('length', 'km', [
            'symbol' => 'km',
            'display_name' => '千米',
            'base_value' => 1000.0,
        ]);
        
        $this->createUnit('length', 'cm', [
            'symbol' => 'cm',
            'display_name' => '厘米',
            'base_value' => 0.01,
        ]);
        
        // 重量单位
        $this->createUnit('weight', 'kg', [
            'symbol' => 'kg',
            'display_name' => '千克',
            'base_value' => 1.0,
            'is_base' => true,
        ]);
        
        $this->createUnit('weight', 'g', [
            'symbol' => 'g',
            'display_name' => '克',
            'base_value' => 0.001,
        ]);
        
        $this->createUnit('weight', 't', [
            'symbol' => 't',
            'display_name' => '吨',
            'base_value' => 1000.0,
        ]);
    }
    
    /**
     * 清除单位缓存
     */
    protected function clearUnitCache(string $code, ?int $categoryId = null): void
    {
        $this->cache->forget(self::CACHE_PREFIX . 'unit:' . $code . ':' . ($categoryId ?? 'any'));
        $this->cache->forget(self::CACHE_PREFIX . 'categories:active');
        
        if ($categoryId) {
            $category = $this->configure->unitCategoryModel::find($categoryId);
            if ($category) {
                $this->cache->forget(self::CACHE_PREFIX . 'category:' . $category->name);
            }
        }
    }
    
    /**
     * 清除转换规则缓存
     */
    protected function clearRuleCache(int $fromUnitId, int $toUnitId): void
    {
        $this->cache->forget(self::CACHE_PREFIX . 'rule:' . $fromUnitId . ':' . $toUnitId);
    }
    
    /**
     * 清除所有相关缓存
     */
    public function clearAllCache(): void
    {
        // 清除类别缓存
        $this->cache->forget(self::CACHE_PREFIX . 'categories:active');
        $this->cache->forget(self::CACHE_PREFIX . 'categories:all');
        
        // 获取所有类别以清除单独的类别缓存
        $categories = $this->configure->unitCategoryModel::all();
        foreach ($categories as $category) {
            $this->cache->forget(self::CACHE_PREFIX . 'category:' . $category->name);
        }
        
        // 注意：由于单位和规则缓存键包含动态ID，无法完全清除
        // 建议使用 Laravel 的缓存标签功能来解决此问题
    }
}
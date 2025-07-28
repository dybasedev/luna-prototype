<?php

namespace Dybasedev\LunaPrototype\DnW\Integrations\AssetsAccount;

use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccount;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Illuminate\Support\Facades\Log;

/**
 * AssetsAccount 集成配置验证器
 * 
 * 用于验证 DnW 与 AssetsAccount 集成的配置是否正确，
 * 特别是在使用单位转换功能时
 */
class ConfigurationValidator
{
    /**
     * 验证单位转换配置
     * 
     * 当启用单位转换时，检查是否正确配置了 ConversionAwareAccountOperations
     * 
     * @param array $handlerConfig 处理器配置
     * @return bool 配置是否有效
     */
    public static function validateUnitConversionSetup(array $handlerConfig): bool
    {
        // 检查是否启用了单位转换
        $enableUnitConversion = $handlerConfig['enable_unit_conversion'] ?? false;
        
        if (!$enableUnitConversion) {
            return true; // 未启用单位转换，无需验证
        }
        
        // 检查 UnitConversion 组件是否可用
        if (!class_exists('\\Dybasedev\\LunaPrototype\\UnitConversion\\LunaUnitConversion')) {
            Log::warning('DnW AssetsAccount Integration: Unit conversion enabled but UnitConversion component not found');
            return false;
        }
        
        // 检查 ConversionAwareAccountOperations 是否可用
        $conversionAwareClass = '\\Dybasedev\\LunaPrototype\\UnitConversion\\Integration\\AssetsAccount\\ConversionAwareAccountOperations';
        if (!class_exists($conversionAwareClass)) {
            Log::warning('DnW AssetsAccount Integration: ConversionAwareAccountOperations class not found');
            return false;
        }
        
        // 检查当前的 AssetsAccount 配置
        try {
            $assetsAccount = app(LunaAssetsAccount::class);
            $currentOperationClass = $assetsAccount->configure->accountOperationClass ?? \Dybasedev\LunaPrototype\AssetsAccount\AccountOperations::class;
            
            // 如果当前操作类不是 ConversionAwareAccountOperations 或其子类
            if ($currentOperationClass !== $conversionAwareClass && !is_subclass_of($currentOperationClass, $conversionAwareClass)) {
                Log::warning('DnW AssetsAccount Integration: Unit conversion enabled but AssetsAccount is not configured to use ConversionAwareAccountOperations', [
                    'current_class' => $currentOperationClass,
                    'required_class' => $conversionAwareClass,
                ]);
                
                // 这是一个警告，不是错误，因为我们会在创建操作时尝试使用正确的类
                return true;
            }
        } catch (\Exception $e) {
            Log::error('DnW AssetsAccount Integration: Failed to check AssetsAccount configuration', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
        
        return true;
    }
    
    /**
     * 验证账户类型配置
     * 
     * @param string $accountType 账户类型
     * @return bool 账户类型是否有效
     */
    public static function validateAccountType(string $accountType): bool
    {
        try {
            $assetsAccount = app(LunaAssetsAccount::class);
            $accountTypeId = is_string($accountType) ? hash_code($accountType) : $accountType;
            $accountTypes = $assetsAccount->getAllAccountTypes();
            
            return $accountTypes->where('id', $accountTypeId)->isNotEmpty();
        } catch (\Exception $e) {
            Log::error('DnW AssetsAccount Integration: Failed to validate account type', [
                'account_type' => $accountType,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    /**
     * 获取配置建议
     * 
     * @param array $handlerConfig 处理器配置
     * @return array 配置建议
     */
    public static function getConfigurationSuggestions(array $handlerConfig): array
    {
        $suggestions = [];
        
        $enableUnitConversion = $handlerConfig['enable_unit_conversion'] ?? false;
        
        if ($enableUnitConversion) {
            // 检查全局配置
            try {
                $assetsAccount = app(LunaAssetsAccount::class);
                $currentOperationClass = $assetsAccount->configure->accountOperationClass ?? \Dybasedev\LunaPrototype\AssetsAccount\AccountOperations::class;
                $conversionAwareClass = '\\Dybasedev\\LunaPrototype\\UnitConversion\\Integration\\AssetsAccount\\ConversionAwareAccountOperations';
                
                if ($currentOperationClass !== $conversionAwareClass && !is_subclass_of($currentOperationClass, $conversionAwareClass)) {
                    $suggestions[] = [
                        'level' => 'warning',
                        'message' => '建议全局配置 AssetsAccount 使用 ConversionAwareAccountOperations',
                        'action' => '在 AppServiceProvider 中添加: ->useAccountOperationClass(ConversionAwareAccountOperations::class)',
                    ];
                }
            } catch (\Exception $e) {
                // 忽略错误
            }
            
            // 检查单位转换配置
            if (!isset($handlerConfig['unit_conversion']['to_unit'])) {
                $suggestions[] = [
                    'level' => 'info',
                    'message' => '未配置目标单位，将使用原始单位',
                    'action' => '在配置中添加 unit_conversion.to_unit',
                ];
            }
        }
        
        return $suggestions;
    }
    
    /**
     * 抛出配置错误
     * 
     * @param string $message 错误消息
     * @throws LunaException
     */
    public static function throwConfigurationError(string $message): void
    {
        throw LunaException::create($message)
            ->withDisplayMessage('DnW AssetsAccount 集成配置错误')
            ->withData(['component' => 'dnw_assets_account_integration'])
            ->withHttpStatus(500);
    }
}
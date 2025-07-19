<?php

namespace Dybasedev\LunaPrototype\UnitConversion\Integration\AssetsAccount;

use Dybasedev\LunaPrototype\AssetsAccount\AccountTypeCreationRequest;
use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccount;
use Dybasedev\LunaPrototype\UnitConversion\LunaUnitConversion;

/**
 * 资产账户集成
 * 
 * 为 AssetsAccount 组件提供多币种支持
 */
class AssetsAccountIntegration
{
    /**
     * 检查单位转换组件是否可用
     */
    public static function isAvailable(): bool
    {
        return luna_unit_conversion() !== null;
    }
    
    /**
     * 为账户类型添加货币单位支持
     */
    public static function addCurrencySupport(AccountTypeCreationRequest $request, string $currencyCode): AccountTypeCreationRequest
    {
        if (!self::isAvailable()) {
            return $request;
        }
        
        $unitConversion = luna_unit_conversion();
        $currency = $unitConversion->getUnit($currencyCode, 'currency');
        
        if (!$currency) {
            return $request;
        }
        
        // 在元数据中添加货币信息
        $metadata = $request->getMetadata();
        $metadata['currency'] = [
            'code' => $currency->code,
            'symbol' => $currency->symbol,
            'display_name' => $currency->display_name,
            'precision' => $currency->precision,
        ];
        
        return $request->withMetadata($metadata);
    }
    
    /**
     * 创建多币种子账户
     */
    public static function createMultiCurrencyAccounts(
        LunaAssetsAccount $assetsAccount,
        string $parentAccountTypeName,
        array $currencyCodes,
        array $baseAttributes = []
    ): array {
        if (!self::isAvailable()) {
            throw new \RuntimeException('Unit conversion module is not available');
        }
        
        $unitConversion = luna_unit_conversion();
        $createdAccounts = [];
        
        foreach ($currencyCodes as $currencyCode) {
            $currency = $unitConversion->getUnit($currencyCode, 'currency');
            if (!$currency) {
                continue;
            }
            
            $attributes = array_merge($baseAttributes, [
                'display_name' => $baseAttributes['display_name'] . ' - ' . $currency->display_name,
                'metadata' => [
                    'currency' => [
                        'code' => $currency->code,
                        'symbol' => $currency->symbol,
                        'display_name' => $currency->display_name,
                        'precision' => $currency->precision,
                    ],
                ],
            ]);
            
            $accountType = $assetsAccount->createAccountType(
                $parentAccountTypeName . '_' . strtolower($currencyCode),
                $attributes['handler_class'] ?? $baseAttributes['handler_class'],
                $attributes['display_name'] ?? null,
                $attributes['description'] ?? null,
                null, // config
                $parentAccountTypeName
            );
            
            $createdAccounts[$currencyCode] = $accountType;
        }
        
        return $createdAccounts;
    }
    
    /**
     * 转换账户余额到指定货币
     */
    public static function convertBalance(
        float $balance,
        string $fromCurrency,
        string $toCurrency,
        array $context = []
    ): ?float {
        if (!self::isAvailable()) {
            return null;
        }
        
        return luna_convert_unit($fromCurrency, $toCurrency, $balance, $context);
    }
    
    /**
     * 获取账户的货币信息
     */
    public static function getAccountCurrency(array $accountMetadata): ?array
    {
        return $accountMetadata['currency'] ?? null;
    }
    
    /**
     * 格式化账户余额
     */
    public static function formatAccountBalance(float $balance, array $accountMetadata): string
    {
        $currency = self::getAccountCurrency($accountMetadata);
        
        if (!$currency) {
            return number_format($balance, 2);
        }
        
        if (self::isAvailable() && isset($currency['code'])) {
            return luna_format_unit_value($balance, $currency['code']);
        }
        
        // 降级处理
        $symbol = $currency['symbol'] ?? '';
        $precision = $currency['precision'] ?? 2;
        
        return $symbol . number_format($balance, $precision);
    }
}
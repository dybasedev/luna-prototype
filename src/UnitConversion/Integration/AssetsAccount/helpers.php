<?php

use Dybasedev\LunaPrototype\UnitConversion\Integration\AssetsAccount\UnitConversionTransferBuilder;
use Dybasedev\LunaPrototype\UnitConversion\Integration\AssetsAccount\ConversionAwareAccountOperations;
use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccount;
use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccountConfigure;

/**
 * 创建支持单位转换的账户转账操作构建器
 * 
 * @return UnitConversionTransferBuilder
 */
function luna_unit_conversion_transfer(): UnitConversionTransferBuilder
{
    $builder = new UnitConversionTransferBuilder();
    
    // 尝试注入依赖
    if ($luna = luna_assets_account()) {
        $builder->withLunaAssetsAccount($luna);
    }
    
    if ($unitConversion = luna_unit_conversion()) {
        $builder->withUnitConversion($unitConversion);
    }
    
    return $builder;
}

/**
 * 创建支持单位转换的账户操作对象
 * 
 * @param LunaAssetsAccount|null $assetsAccount 资产账户实例
 * @return ConversionAwareAccountOperations
 */
function luna_conversion_aware_operations(?LunaAssetsAccount $assetsAccount = null): ConversionAwareAccountOperations
{
    $assetsAccount = $assetsAccount ?? luna_assets_account();
    
    if (!$assetsAccount) {
        throw new \RuntimeException('Assets account module is not available');
    }
    
    $configure = app(LunaAssetsAccountConfigure::class);
    $connection = app('db')->connection();
    
    return new ConversionAwareAccountOperations($configure, $connection);
}
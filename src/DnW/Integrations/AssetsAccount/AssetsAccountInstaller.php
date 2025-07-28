<?php

namespace Dybasedev\LunaPrototype\DnW\Integrations\AssetsAccount;

use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\Configuration\Repository;
use Dybasedev\LunaPrototype\DnW\LunaDnW;
use Dybasedev\LunaPrototype\DnW\Models\DepositChannel;
use Dybasedev\LunaPrototype\DnW\Models\WithdrawChannel;
use Dybasedev\LunaPrototype\DnW\Integrations\AssetsAccount\ConfigurationValidator;

/**
 * AssetsAccount 集成安装器
 * 
 * 提供便捷的方法来安装 AssetsAccount 相关的处理器和渠道
 */
class AssetsAccountInstaller
{
    /**
     * 安装默认的入金处理器
     */
    public static function installDepositHandler(): int
    {
        $lunaHandler = app(LunaHandler::class);
        
        $config = new Repository([
            'account_type' => 'balance', // 默认使用 balance 账户类型
        ]);
        
        $handler = $lunaHandler->createEntityHandler(
            'dnw',
            'assets_account_deposit_handler',
            AssetsAccountDepositHandler::class,
            $config,
            '资产账户入金处理器',
            '与 AssetsAccount 组件集成的入金处理器'
        );

        return $handler->id;
    }

    /**
     * 安装默认的出金处理器
     */
    public static function installWithdrawHandler(): int
    {
        $lunaHandler = app(LunaHandler::class);
        
        $config = new Repository([
            'account_type' => 'balance', // 默认使用 balance 账户类型
        ]);
        
        $handler = $lunaHandler->createEntityHandler(
            'dnw',
            'assets_account_withdraw_handler',
            AssetsAccountWithdrawHandler::class,
            $config,
            '资产账户出金处理器',
            '与 AssetsAccount 组件集成的出金处理器'
        );

        return $handler->id;
    }

    /**
     * 安装默认的入金渠道
     */
    public static function installDefaultDepositChannel(): DepositChannel
    {
        $handlerId = static::installDepositHandler();
        $lunaDnW = app(LunaDnW::class);
        
        // 检查是否已存在
        $existing = DepositChannel::where('name', 'assets_account_deposit')->first();
        if ($existing) {
            return $existing;
        }
        
        return $lunaDnW->createDepositChannel(
            'assets_account_deposit',
            $handlerId,
            [
                'description' => '资产账户入金，支持自动确认和人工确认',
                'supported_currencies' => ['CNY'],
            ],
            [], // metadata
            true, // isActive
            100 // sortOrder
        );
    }

    /**
     * 安装默认的出金渠道
     */
    public static function installDefaultWithdrawChannel(): WithdrawChannel
    {
        $handlerId = static::installWithdrawHandler();
        $lunaDnW = app(LunaDnW::class);
        
        // 检查是否已存在
        $existing = WithdrawChannel::where('name', 'assets_account_withdraw')->first();
        if ($existing) {
            return $existing;
        }
        
        return $lunaDnW->createWithdrawChannel(
            'assets_account_withdraw',
            $handlerId,
            [
                'description' => '资产账户出金，支持多种提现方式',
                'supported_currencies' => ['CNY'],
            ],
            [], // metadata
            true, // isActive
            100 // sortOrder
        );
    }

    /**
     * 完整安装
     * 
     * 安装处理器和默认渠道
     */
    public static function install(): array
    {
        $depositChannel = static::installDefaultDepositChannel();
        $withdrawChannel = static::installDefaultWithdrawChannel();
        
        return [
            'deposit_channel' => $depositChannel,
            'withdraw_channel' => $withdrawChannel,
        ];
    }

    /**
     * 创建测试渠道
     * 
     * 创建用于测试的入金和出金渠道
     */
    public static function installTestChannels(): array
    {
        $lunaHandler = app(LunaHandler::class);
        $lunaDnW = app(LunaDnW::class);
        
        // 测试入金处理器
        $testDepositConfig = new Repository([
            'account_type' => 'balance', // 使用 balance 账户类型
        ]);
        
        $testDepositHandler = $lunaHandler->createEntityHandler(
            'dnw',
            'test_assets_deposit_handler',
            AssetsAccountDepositHandler::class,
            $testDepositConfig,
            '测试入金处理器',
            '用于测试的自动确认入金处理器'
        );

        // 检查入金渠道是否已存在
        $existingDepositChannel = DepositChannel::where('name', 'test_assets_deposit')->first();
        if (!$existingDepositChannel) {
            $testDepositChannel = $lunaDnW->createDepositChannel(
                'test_assets_deposit',
                $testDepositHandler->id,
                [
                    'description' => '测试入金渠道（自动确认）',
                    'is_test' => true,
                ],
                [], // metadata
                true, // isActive
                999 // sortOrder
            );
        } else {
            $testDepositChannel = $existingDepositChannel;
        }

        // 测试出金处理器
        $testWithdrawConfig = new Repository([
            'account_type' => 'balance', // 使用 balance 账户类型
        ]);
        
        $testWithdrawHandler = $lunaHandler->createEntityHandler(
            'dnw',
            'test_assets_withdraw_handler',
            AssetsAccountWithdrawHandler::class,
            $testWithdrawConfig,
            '测试出金处理器',
            '用于测试的出金处理器'
        );

        // 检查出金渠道是否已存在
        $existingWithdrawChannel = WithdrawChannel::where('name', 'test_assets_withdraw')->first();
        if (!$existingWithdrawChannel) {
            $testWithdrawChannel = $lunaDnW->createWithdrawChannel(
                'test_assets_withdraw',
                $testWithdrawHandler->id,
                [
                    'description' => '测试出金渠道',
                    'is_test' => true,
                ],
                [], // metadata
                true, // isActive
                999 // sortOrder
            );
        } else {
            $testWithdrawChannel = $existingWithdrawChannel;
        }

        return [
            'test_deposit_channel' => $testDepositChannel,
            'test_withdraw_channel' => $testWithdrawChannel,
        ];
    }
    
    /**
     * 创建带单位转换的入金处理器
     * 
     * 创建一个支持货币转换的入金处理器
     * 
     * @param string $fromUnit 源货币单位
     * @param string $toUnit 目标货币单位
     * @param string $accountType 账户类型
     * @return int 处理器ID
     */
    public static function installCurrencyConversionHandler(
        string $fromUnit = 'USD',
        string $toUnit = 'CNY',
        string $accountType = 'balance'
    ): int {
        $lunaHandler = app(LunaHandler::class);
        
        $handlerName = sprintf(
            'assets_account_%s_to_%s_deposit_handler',
            strtolower($fromUnit),
            strtolower($toUnit)
        );
        
        $config = new Repository([
            'account_type' => $accountType,
            'enable_unit_conversion' => true,
            'unit_conversion' => [
                'from_unit' => $fromUnit,
                'to_unit' => $toUnit,
            ],
        ]);
        
        $handler = $lunaHandler->createEntityHandler(
            'dnw',
            $handlerName,
            AssetsAccountDepositHandler::class,
            $config,
            sprintf('%s 到 %s 入金处理器', $fromUnit, $toUnit),
            sprintf('支持从 %s 转换到 %s 的入金处理器', $fromUnit, $toUnit)
        );

        return $handler->id;
    }
    
    /**
     * 检查并显示配置建议
     * 
     * @param array $config 处理器配置
     * @return void
     */
    public static function checkConfiguration(array $config = []): void
    {
        if (!ConfigurationValidator::validateUnitConversionSetup($config)) {
            echo "\n⚠️  配置验证失败\n";
        }
        
        $suggestions = ConfigurationValidator::getConfigurationSuggestions($config);
        
        if (!empty($suggestions)) {
            echo "\n💡 配置建议：\n";
            foreach ($suggestions as $suggestion) {
                $icon = $suggestion['level'] === 'warning' ? '⚠️' : 'ℹ️';
                echo "{$icon} {$suggestion['message']}\n";
                if (isset($suggestion['action'])) {
                    echo "   操作: {$suggestion['action']}\n";
                }
            }
            echo "\n";
        }
    }
}
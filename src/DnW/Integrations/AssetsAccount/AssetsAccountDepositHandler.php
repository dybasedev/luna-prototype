<?php

namespace Dybasedev\LunaPrototype\DnW\Integrations\AssetsAccount;

use Dybasedev\LunaPrototype\DnW\Handlers\BaseDepositHandler;
use Dybasedev\LunaPrototype\DnW\Models\DepositTransaction;
use Dybasedev\LunaPrototype\DnW\DepositResult;
use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccount;
use Dybasedev\LunaPrototype\DnW\Integrations\AssetsAccount\ConfigurationValidator;

/**
 * 资产账户入金处理器
 * 
 * 与 LunaAssetsAccount 组件集成，入金到指定的账户类型
 */
class AssetsAccountDepositHandler extends BaseDepositHandler
{
    /**
     * 获取处理器名称
     */
    public function getName(): string
    {
        return '资产账户入金';
    }

    /**
     * 获取处理器描述
     */
    public function getDescription(): string
    {
        return '入金到指定的 AssetsAccount 账户类型';
    }

    /**
     * 获取支持的绑定类型
     */
    public function getSupportedBindingTypes(): array
    {
        return []; // 不需要绑定，直接操作账户
    }

    /**
     * 执行具体的处理逻辑
     */
    protected function doProcess(DepositTransaction $transaction): DepositResult
    {
        // 验证配置
        if (!ConfigurationValidator::validateUnitConversionSetup($this->config?->all() ?? [])) {
            $this->log('Configuration validation failed for unit conversion');
        }
        
        try {
            // 获取账户类型配置
            $accountType = $this->getAccountType();
            
            // 创建账户操作
            $assetsAccount = app(LunaAssetsAccount::class);
            
            // 检查是否启用了单位转换并确保使用正确的操作类
            $operation = $this->createAccountOperation($assetsAccount);
            
            // 构建入金操作
            $operation->operation(
                luna_account_update()
                    ->account($transaction->owner, $accountType)
                    ->available()
                    ->event('dnw_deposit')
                    ->payload([
                        'transaction_id' => $transaction->id,
                        'channel_id' => $transaction->channel_id,
                        'original_amount' => $transaction->amount,
                        'fee' => $transaction->fee,
                    ])
                    ->increase($transaction->getNetAmount())
            );
            
            // 提交操作
            $operation->submit();
            
            // 标记交易成功
            $transaction->markAsSuccess([
                'account_type' => $accountType,
                'completed_at' => now()->toDateTimeString(),
            ]);
            
            return DepositResult::success(
                completed: true,
                extra: [
                    'account_type' => $accountType,
                    'net_amount' => $transaction->getNetAmount(),
                ]
            );
        } catch (\Exception $e) {
            $this->log('Assets account deposit failed', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
            
            return DepositResult::failed($e->getMessage());
        }
    }

    /**
     * 获取配置的账户类型
     */
    protected function getAccountType(): string
    {
        if (!$this->config) {
            throw new \RuntimeException('Account type not configured');
        }

        $accountType = $this->config->get('account_type');
        
        if (!$accountType) {
            throw new \RuntimeException('Account type not specified in handler configuration');
        }

        return $accountType;
    }

    /**
     * 记录日志
     */
    protected function log(string $message, array $context = []): void
    {
        \Illuminate\Support\Facades\Log::info("AssetsAccountDepositHandler: {$message}", $context);
    }
    
    /**
     * 预处理金额
     * 
     * 支持通过配置启用单位转换
     * 
     * @param string $amount 原始金额
     * @param array $options 选项参数
     * @return string 处理后的金额
     */
    protected function preprocessAmount(string $amount, array $options = []): string
    {
        // 检查是否启用了单位转换
        $enableUnitConversion = $this->config?->get('enable_unit_conversion', false) ?? false;
        
        if (!$enableUnitConversion) {
            return parent::preprocessAmount($amount, $options);
        }
        
        // 获取单位转换配置
        $conversionConfig = $this->config?->get('unit_conversion', []) ?? [];
        
        // 检查是否存在 LunaUnitConversion 组件
        if (!class_exists('\\Dybasedev\\LunaPrototype\\UnitConversion\\LunaUnitConversion')) {
            $this->log('Unit conversion enabled but LunaUnitConversion component not found');
            return $amount;
        }
        
        try {
            $unitConversion = app('\\Dybasedev\\LunaPrototype\\UnitConversion\\LunaUnitConversion');
            
            // 获取源单位和目标单位
            $fromUnit = $options['from_unit'] ?? $conversionConfig['from_unit'] ?? null;
            $toUnit = $conversionConfig['to_unit'] ?? null;
            
            if ($fromUnit && $toUnit && $fromUnit !== $toUnit) {
                // 执行单位转换
                $convertedAmount = $unitConversion->convert($amount, $fromUnit, $toUnit);
                
                $this->log('Amount converted', [
                    'original_amount' => $amount,
                    'from_unit' => $fromUnit,
                    'to_unit' => $toUnit,
                    'converted_amount' => $convertedAmount,
                ]);
                
                return $convertedAmount;
            }
        } catch (\Exception $e) {
            $this->log('Unit conversion failed', [
                'error' => $e->getMessage(),
                'amount' => $amount,
            ]);
        }
        
        return $amount;
    }
    
    /**
     * 创建账户操作实例
     * 
     * 当启用单位转换时，确保使用 ConversionAwareAccountOperations
     * 
     * @param LunaAssetsAccount $assetsAccount
     * @return \Dybasedev\LunaPrototype\AssetsAccount\AccountOperations
     */
    protected function createAccountOperation(LunaAssetsAccount $assetsAccount)
    {
        $enableUnitConversion = $this->config?->get('enable_unit_conversion', false) ?? false;
        
        if ($enableUnitConversion) {
            // 检查 ConversionAwareAccountOperations 类是否存在
            $conversionAwareClass = '\\Dybasedev\\LunaPrototype\\UnitConversion\\Integration\\AssetsAccount\\ConversionAwareAccountOperations';
            if (class_exists($conversionAwareClass)) {
                return $assetsAccount->createAccountOperation($conversionAwareClass);
            } else {
                $this->log('Unit conversion enabled but ConversionAwareAccountOperations class not found', [
                    'handler' => static::class,
                ]);
            }
        }
        
        // 使用默认的操作类
        return $assetsAccount->createAccountOperation();
    }

    /**
     * 获取处理器唯一标识名
     */
    public function handlerName(): string
    {
        return 'assets_account_deposit';
    }

    /**
     * 获取处理器说明
     */
    public function handlerDescription(): string
    {
        return $this->getDescription();
    }
}
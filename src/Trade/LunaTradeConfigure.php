<?php

namespace Dybasedev\LunaPrototype\Trade;

use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandlerConfigure;
use Dybasedev\LunaPrototype\Foundation\BusinessEvent\LunaBusinessEventConfigure;
use Dybasedev\LunaPrototype\Trade\Models\TradeTransaction;
use Dybasedev\LunaPrototype\Trade\Models\TradeTransactionTradable;
use Dybasedev\LunaPrototype\Trade\Models\TradeTradable;
use Dybasedev\LunaPrototype\Trade\Standard\StandardTradeFlowHandler;
use Dybasedev\LunaPrototype\Trade\Standard\StandardTransactionNumberGenerator;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;

/**
 * 交易组件配置类
 *
 * 提供交易组件的配置和注册功能，支持模型替换、处理器注册等扩展。
 *
 * @package Dybasedev\LunaPrototype\Trade
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class LunaTradeConfigure extends LunaModuleConfigure
{
    /**
     * @var class-string<TradeTransaction>
     */
    protected(set) string $transactionModel = TradeTransaction::class;

    /**
     * @var class-string<TradeTransactionTradable>
     */
    protected(set) string $transactionTradableModel = TradeTransactionTradable::class;

    /**
     * @var class-string<TradeTradable>
     */
    protected(set) string $tradableModel = TradeTradable::class;

    /**
     * @var string 默认交易号前缀
     */
    protected(set) string $defaultTransactionNumberPrefix = 'T';

    /**
     * @var class-string<TransactionNumberGenerator>|null 默认交易编号生成器类
     */
    protected(set) ?string $defaultTransactionNumberGeneratorClass = StandardTransactionNumberGenerator::class;

    /**
     * @var TransactionNumberGenerator|null 缓存的交易编号生成器实例
     */
    private ?TransactionNumberGenerator $_transactionNumberGenerator = null;

    /**
     * 全局默认交易编号生成器（延迟初始化）
     */
    protected(set) ?TransactionNumberGenerator $transactionNumberGenerator {
        get {
            if ($this->_transactionNumberGenerator === null && $this->defaultTransactionNumberGeneratorClass !== null) {
                $this->_transactionNumberGenerator = new $this->defaultTransactionNumberGeneratorClass($this->defaultTransactionNumberPrefix);
            }
            return $this->_transactionNumberGenerator;
        }
        set {
            $this->_transactionNumberGenerator = $value;
        }
    }

    /**
     * 获取全局默认交易编号生成器
     *
     * @return TransactionNumberGenerator|null
     */
    public function getTransactionNumberGenerator(): ?TransactionNumberGenerator
    {
        return $this->transactionNumberGenerator;
    }

    /**
     * @var bool 是否启用交易过期检查
     */
    protected(set) bool $enableExpiredCheck = true;

    /**
     * @var int 交易过期检查间隔（分钟）
     */
    protected(set) int $expiredCheckInterval = 30;



    public function name(): string
    {
        return 'luna.trade';
    }

    public function serviceProvider(): ?string
    {
        return LunaTradeServiceProvider::class;
    }

    /**
     * 替换默认的交易模型
     *
     * @param class-string<TradeTransaction> $class
     * @return $this
     */
    public function useTransactionModel(string $class): static
    {
        $this->transactionModel = $class;
        return $this;
    }

    /**
     * 替换默认的交易可交易对象关联模型
     *
     * @param class-string<TradeTransactionTradable> $class
     * @return $this
     */
    public function useTransactionTradableModel(string $class): static
    {
        $this->transactionTradableModel = $class;
        return $this;
    }

    /**
     * 替换默认的可交易对象模型
     *
     * @param class-string<TradeTradable> $class
     * @return $this
     */
    public function useTradableModel(string $class): static
    {
        $this->tradableModel = $class;
        return $this;
    }

    /**
     * 设置默认交易号前缀
     *
     * @param string $prefix
     * @return $this
     */
    public function setDefaultTransactionNumberPrefix(string $prefix): static
    {
        $this->defaultTransactionNumberPrefix = $prefix;
        return $this;
    }

    /**
     * 设置默认交易编号生成器类
     *
     * @param class-string<TransactionNumberGenerator> $generatorClass
     * @return $this
     */
    public function setDefaultTransactionNumberGeneratorClass(string $generatorClass): static
    {
        $this->defaultTransactionNumberGeneratorClass = $generatorClass;
        $this->_transactionNumberGenerator = null; // 清除缓存的实例
        return $this;
    }

    /**
     * 启用或禁用交易过期检查
     *
     * @param bool $enable
     * @param int|null $interval 检查间隔（分钟）
     * @return $this
     */
    public function enableExpiredCheck(bool $enable, ?int $interval = null): static
    {
        $this->enableExpiredCheck = $enable;
        if ($interval !== null) {
            $this->expiredCheckInterval = $interval;
        }
        return $this;
    }



    public function register(Container $container): void
    {
        $container->singleton('luna.trade', function ($app) {
            return new LunaTrade(
                $app->make(LunaTradeConfigure::class),
                $app->make(LunaHandler::class),
                $app->make('cache.store'),
            );
        });

        $container->alias('luna.trade', LunaTrade::class);
    }

    /**
     * @throws BindingResolutionException
     */
    public function boot(Container $container): void
    {
        // 注册业务事件组
        $container->make(LunaBusinessEventConfigure::class)->group('trade', '交易事件');

        // 注册处理器组（仅注册组，具体的处理器由使用方自行注册）
        $container->make(LunaHandlerConfigure::class)->group('trade', '交易', function ($register) {
            $register->handler(StandardTradeFlowHandler::class);
        });
    }
}

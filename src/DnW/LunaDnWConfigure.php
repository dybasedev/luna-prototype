<?php

namespace Dybasedev\LunaPrototype\DnW;

use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandlerConfigure;
use Dybasedev\LunaPrototype\Foundation\BusinessEvent\LunaBusinessEventConfigure;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Contracts\Container\Container;

/**
 * 出入金模块配置类
 */
class LunaDnWConfigure extends LunaModuleConfigure
{
    /**
     * @var class-string<Models\DepositChannel>
     */
    protected(set) string $depositChannelModel = Models\DepositChannel::class;

    /**
     * @var class-string<Models\WithdrawChannel>
     */
    protected(set) string $withdrawChannelModel = Models\WithdrawChannel::class;

    /**
     * @var class-string<Models\DepositTransaction>
     */
    protected(set) string $depositTransactionModel = Models\DepositTransaction::class;

    /**
     * @var class-string<Models\WithdrawTransaction>
     */
    protected(set) string $withdrawTransactionModel = Models\WithdrawTransaction::class;

    /**
     * @var class-string<Models\DepositTransactionLog>
     */
    protected(set) string $depositTransactionLogModel = Models\DepositTransactionLog::class;

    /**
     * @var class-string<Models\WithdrawTransactionLog>
     */
    protected(set) string $withdrawTransactionLogModel = Models\WithdrawTransactionLog::class;

    /**
     * @var class-string<Models\DepositBinding>
     */
    protected(set) string $depositBindingModel = Models\DepositBinding::class;

    /**
     * @var class-string<Models\WithdrawBinding>
     */
    protected(set) string $withdrawBindingModel = Models\WithdrawBinding::class;

    /**
     * @var bool 是否启用出金审核
     */
    public protected(set) bool $enableWithdrawReview = true;

    /**
     * @var float 免审核金额阈值
     */
    public protected(set) float $withdrawReviewThreshold = 10000.00;

    /**
     * @var bool 是否启用交易日志
     */
    public protected(set) bool $enableTransactionLog = true;

    /**
     * @var bool 是否启用绑定验证
     */
    public protected(set) bool $requireBindingVerification = false;

    public function name(): string
    {
        return 'luna.dnw';
    }

    public function serviceProvider(): ?string
    {
        return LunaDnWServiceProvider::class;
    }

    /**
     * 使用自定义的入金渠道模型
     */
    public function useDepositChannelModel(string $model): static
    {
        $this->depositChannelModel = $model;
        return $this;
    }

    /**
     * 使用自定义的出金渠道模型
     */
    public function useWithdrawChannelModel(string $model): static
    {
        $this->withdrawChannelModel = $model;
        return $this;
    }

    /**
     * 使用自定义的入金交易模型
     */
    public function useDepositTransactionModel(string $model): static
    {
        $this->depositTransactionModel = $model;
        return $this;
    }

    /**
     * 使用自定义的出金交易模型
     */
    public function useWithdrawTransactionModel(string $model): static
    {
        $this->withdrawTransactionModel = $model;
        return $this;
    }

    /**
     * 使用自定义的入金交易日志模型
     */
    public function useDepositTransactionLogModel(string $model): static
    {
        $this->depositTransactionLogModel = $model;
        return $this;
    }

    /**
     * 使用自定义的出金交易日志模型
     */
    public function useWithdrawTransactionLogModel(string $model): static
    {
        $this->withdrawTransactionLogModel = $model;
        return $this;
    }

    /**
     * 使用自定义的入金绑定模型
     */
    public function useDepositBindingModel(string $model): static
    {
        $this->depositBindingModel = $model;
        return $this;
    }

    /**
     * 使用自定义的出金绑定模型
     */
    public function useWithdrawBindingModel(string $model): static
    {
        $this->withdrawBindingModel = $model;
        return $this;
    }

    /**
     * 设置出金审核配置
     */
    public function setWithdrawReview(bool $enable, float $threshold = 10000.00): static
    {
        $this->enableWithdrawReview = $enable;
        $this->withdrawReviewThreshold = $threshold;
        return $this;
    }

    /**
     * 启用/禁用交易日志
     */
    public function setTransactionLog(bool $enable): static
    {
        $this->enableTransactionLog = $enable;
        return $this;
    }

    /**
     * 设置绑定验证要求
     */
    public function setBindingVerification(bool $require): static
    {
        $this->requireBindingVerification = $require;
        return $this;
    }

    /**
     * 注册服务
     */
    public function register(Container $container): void
    {
        $container->singleton('luna.dnw', function ($app) {
            return new LunaDnW(
                $app->make(LunaDnWConfigure::class)
            );
        });

        $container->alias('luna.dnw', LunaDnW::class);
    }

    /**
     * 启动服务
     */
    public function boot(Container $container): void
    {
        // 注册业务事件组
        $container->make(LunaBusinessEventConfigure::class)->group('dnw', '出入金事件');

        // 注册处理器组
        $container->make(LunaHandlerConfigure::class)->group('dnw', '出入金');
        
        // 注册 morph 映射
        $morphMap = [];
        
        // 收集所有可能的 morph 类
        $morphClasses = [
            $this->depositTransactionModel,
            $this->withdrawTransactionModel,
            $this->depositBindingModel,
            $this->withdrawBindingModel,
        ];
        
        foreach ($morphClasses as $class) {
            if (class_exists($class)) {
                $morphMap[(string)hash_code($class)] = $class;
            }
        }
        
        // 注册到 Eloquent
        Relation::morphMap($morphMap);
    }
}
<?php

namespace Dybasedev\LunaPrototype\AssetsAccount;

use Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccount;
use Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccountChangeLog;
use Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccountType;
use Dybasedev\LunaPrototype\Foundation\BusinessEvent\LunaBusinessEventConfigure;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandlerConfigure;
use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;

/**
 * Luna 资产账户模块配置类
 * 
 * 提供多层级账户管理、余额管理、交易记录等功能
 * 支持自定义账户类型、货币类型、交易处理器等
 * 
 * @package Dybasedev\LunaPrototype\AssetsAccount
 */
class LunaAssetsAccountConfigure extends LunaModuleConfigure
{
    /**
     * 资产账户模型类名
     * 
     * @var class-string<AssetsAccount>
     */
    protected(set) string $accountModel = AssetsAccount::class;

    /**
     * 账户变动日志模型类名
     * 
     * @var class-string<AssetsAccountChangeLog>
     */
    protected(set) string $accountChangeLogModel = AssetsAccountChangeLog::class;

    /**
     * 账户类型模型类名
     * 
     * @var class-string<AssetsAccountType>
     */
    protected(set) string $accountTypeModel = AssetsAccountType::class;

    /**
     * 绑定资产账户的对象集合
     * 
     * @var AssetsAccountBinding[]
     */
    protected(set) array $bindings = [];
    
    /**
     * 自定义的账户操作类
     * 
     * @var class-string<AccountOperations>|null
     */
    protected(set) ?string $accountOperationClass = null;

    /**
     * 获取模块名称
     * 
     * @return string
     */
    public function name(): string
    {
        return 'luna.assets-account';
    }

    /**
     * 获取服务提供者类名
     * 
     * @return string|null
     */
    public function serviceProvider(): ?string
    {
        return LunaAssetsAccountServiceProvider::class;
    }

    /**
     * 替换默认的账户模型
     *
     * @param class-string<AssetsAccount> $class
     * @return $this
     */
    public function useAccountModel(string $class): static
    {
        $this->accountModel = $class;
        return $this;
    }

    /**
     * 替换默认的账户日志模型
     *
     * @param class-string<AssetsAccountChangeLog> $class
     * @return $this
     */
    public function useAccountChangeLogModel(string $class): static
    {
        $this->accountChangeLogModel = $class;
        return $this;
    }

    /**
     * 替换默认的账户类型模型
     *
     * @param class-string<AssetsAccountType> $class
     * @return $this
     */
    public function useAccountTypeModel(string $class): static
    {
        $this->accountTypeModel = $class;
        return $this;
    }

    /**
     * 添加一个资产账户的绑定对象
     *
     * @param AssetsAccountBinding $binding
     * @return $this
     */
    public function bind(AssetsAccountBinding $binding): static
    {
        $this->bindings[] = $binding;
        return $this;
    }
    
    /**
     * 设置自定义的账户操作类
     *
     * @param class-string<AccountOperations> $class
     * @return $this
     */
    public function useAccountOperationClass(string $class): static
    {
        $this->accountOperationClass = $class;
        return $this;
    }

    /**
     * 注册资产账户服务到容器
     * 
     * @param Container $container
     * @return void
     */
    public function register(Container $container): void
    {
        $container->singleton('luna.assets-account', function ($app) {
            return new LunaAssetsAccount(
                $app->make(LunaAssetsAccountConfigure::class),
                $app->make(LunaHandler::class),
                $app->make('cache.store'),
            );
        });

        $container->alias('luna.assets-account', LunaAssetsAccount::class);
    }

    /**
     * 启动资产账户模块
     * 
     * 注册账户事件组和默认的标准账户处理器
     * 
     * @param Container $container
     * @return void
     * @throws BindingResolutionException
     */
    public function boot(Container $container): void
    {
        $container->make(LunaBusinessEventConfigure::class)->group('account', '账户事件');
        $container->make(LunaHandlerConfigure::class)->group('account', '账户', function ($register) {
            $register->handler(StandardAccountHandler::class);
        });
    }


}
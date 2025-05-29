<?php

namespace Dybasedev\LunaPrototype\AssetsAccount;

use Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccount;
use Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccountChangeLog;
use Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccountType;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;
use Illuminate\Contracts\Container\Container;

class LunaAssetsAccountConfigure extends LunaModuleConfigure
{
    /**
     * @var class-string<AssetsAccount>
     */
    protected(set) string $accountModel = AssetsAccount::class;

    /**
     * @var class-string<AssetsAccountChangeLog>
     */
    protected(set) string $accountChangeLogModel = AssetsAccountChangeLog::class;

    /**
     * @var class-string<AssetsAccountType>
     */
    protected(set) string $accountTypeModel = AssetsAccountType::class;

    /**
     * @var AssetsAccountBinding[] 绑定资产账户的对象
     */
    protected(set) array $bindings = [];

    public function name(): string
    {
        return 'luna.assets-account';
    }

    public function serviceProvider(): ?string
    {
        return LunaAssetsAccountServiceProvider::class;
    }

    /**
     * @param class-string<AssetsAccount> $class
     * @return $this
     */
    public function useAccountModel(string $class): static
    {
        $this->accountModel = $class;
        return $this;
    }

    /**
     * @param class-string<AssetsAccountChangeLog> $class
     * @return $this
     */
    public function useAccountChangeLogModel(string $class): static
    {
        $this->accountChangeLogModel = $class;
        return $this;
    }

    /**
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


}
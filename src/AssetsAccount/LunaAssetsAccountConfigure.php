<?php

namespace Dybasedev\LunaPrototype\AssetsAccount;

use Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccount;
use Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccountChangeLog;
use Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccountType;
use Dybasedev\LunaPrototype\Foundation\LunaModuleConfigure;

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


}
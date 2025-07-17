<?php

namespace Dybasedev\LunaPrototype\AssetsAccount;

use Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccountType;
use Dybasedev\LunaPrototype\Foundation\Configuration\Repository;

/**
 * 账户类型创建请求参数对象
 *
 * 使用参数对象模式封装账户类型创建所需的所有参数，
 * 提高代码可读性并简化方法签名。
 */
class AccountTypeCreationRequest
{
    /**
     * 创建账户类型创建请求
     *
     * @param string $name 账户类型名称，必须唯一
     * @param string|int $handler 处理器名称或 ID
     * @param string|null $displayName 显示名称，如果为 null 则使用 name
     * @param string|null $description 描述信息
     * @param Repository|null $config 配置对象，如果为 null 则使用默认配置
     * @param string|int|AssetsAccountType|null $parent 父账户类型
     */
    public function __construct(
        public readonly string $name,
        public readonly string|int $handler,
        public readonly ?string $displayName = null,
        public readonly ?string $description = '',
        public readonly ?Repository $config = null,
        public readonly string|int|AssetsAccountType|null $parent = null,
    ) {
    }

    /**
     * 获取有效的显示名称
     *
     * @return string
     */
    public function getEffectiveDisplayName(): string
    {
        return $this->displayName ?? $this->name;
    }

    /**
     * 获取有效的描述信息
     *
     * @return string
     */
    public function getEffectiveDescription(): string
    {
        return $this->description ?? '';
    }

    /**
     * 获取有效的配置对象
     *
     * @return Repository
     */
    public function getEffectiveConfig(): Repository
    {
        return $this->config ?? new AccountHandlerConfigurationRepository([]);
    }
}
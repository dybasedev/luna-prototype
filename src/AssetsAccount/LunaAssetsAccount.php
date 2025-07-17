<?php

namespace Dybasedev\LunaPrototype\AssetsAccount;

use Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccount;
use Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccountType;
use Dybasedev\LunaPrototype\Foundation\Configuration\Repository;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\LunaModule;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Foundation\SessionHolderBinding;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * 资产账户管理对象
 *
 * 这是 Luna 框架中资产账户系统的核心管理类，提供了完整的资产账户管理功能。
 * 该类负责管理账户类型、用户账户的创建、查询和操作。
 *
 * 主要功能包括：
 * - 创建和管理账户类型
 * - 为用户创建资产账户
 * - 查询账户信息
 * - 提供账户操作接口
 * - 支持账户层级结构（父子账户）
 *
 * 系统设计特点：
 * - 支持多种账户类型（余额、积分、信用等）
 * - 支持账户层级结构管理
 * - 提供缓存机制提高性能
 * - 支持事务安全的账户操作
 * - 集成处理器系统进行扩展
 *
 * @package Dybasedev\LunaPrototype\AssetsAccount
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class LunaAssetsAccount extends LunaModule
{
    /**
     * 资产账户管理对象构造函数
     *
     * @param LunaAssetsAccountConfigure $configure 资产账户配置对象
     * @param LunaHandler $handler 处理器管理对象
     * @param Cache $cache 缓存接口实例
     */
    public function __construct(
        protected(set) LunaAssetsAccountConfigure $configure,
        protected(set) LunaHandler $handler,
        protected Cache $cache
    ) {

    }

    /**
     * 创建账户类型实例
     *
     * 这是一个内部方法，用于创建账户类型的数据库记录实例。
     * 它处理父子关系的建立、配置数据的存储等底层操作。
     *
     * @param string $name 账户类型名称
     * @param string|int $handler 处理器名称或 ID
     * @param string|null $displayName 显示名称
     * @param string|null $description 描述信息
     * @param Repository|null $config 配置对象
     * @param string|int|AssetsAccountType|null $parent 父账户类型
     * @return AssetsAccountType 创建的账户类型实例
     * @throws LunaException 当父账户类型不存在时抛出
     */
    protected function createAccountTypeInstance(
        string $name,
        string|int $handler,
        ?string $displayName = null,
        ?string $description = '',
        ?Repository $config = null,
        string|int|AssetsAccountType|null $parent = null,
    ): AssetsAccountType {
        // 处理父账户类型 ID
        $parentAccountTypeId = 0;
        if ($parent) {
            try {
                if ($parent instanceof AssetsAccountType) {
                    $parentAccountTypeId = $parent->id;
                    if (!$parentAccountTypeId) {
                        throw LunaException::create('Parent account type ID is invalid')
                            ->withDisplayMessage('父账户类型 ID 无效')
                            ->withHttpStatus(400);
                    }
                } else {
                    if (is_string($parent)) {
                        if (trim($parent) === '') {
                            throw LunaException::create('Parent account type name cannot be empty')
                                ->withDisplayMessage('父账户类型名称不能为空')
                                ->withHttpStatus(400);
                        }
                        $parentAccountTypeId = hash_code($parent);
                    } elseif (is_int($parent)) {
                        $parentAccountTypeId = $parent;
                    } else {
                        throw LunaException::create('Invalid parent account type format')
                            ->withDisplayMessage('父账户类型格式无效')
                            ->withHttpStatus(400);
                    }
                    
                    // 检查父级账户类型是否存在
                    if (!$this->getAllAccountTypesWithoutCache()->where('id', $parentAccountTypeId)->count()) {
                        throw LunaException::create('Parent account type not exists')
                            ->withDisplayMessage('父账户类型不存在')
                            ->withData(['parent_id' => $parentAccountTypeId])
                            ->withHttpStatus(404);
                    }
                }
            } catch (Throwable $e) {
                if ($e instanceof LunaException) {
                    throw $e;
                }
                throw LunaException::create($e)
                    ->withDisplayMessage('处理父账户类型时发生错误')
                    ->withHttpStatus(500);
            }
        }

        // 创建账户类型实例并填充数据
        try {
            /** @var AssetsAccountType $instance */
            $instance = new ($this->configure->accountTypeModel)();
            
            $handlerId = is_string($handler) ? hash_code($handler) : $handler;
            
            $instance->forceFill([
                'parent_id' => $parentAccountTypeId,
                'name' => $name,
                'display_name' => $displayName ?? $name,
                'description' => $description ?? '',
                'handler_id' => $handlerId,
                'config' => $config->all(),
            ]);

            // 保存到数据库
            if (!$instance->save()) {
                throw LunaException::create('Failed to save account type')
                    ->withDisplayMessage('保存账户类型失败')
                    ->withData([
                        'name' => $name,
                        'handler_id' => $handlerId,
                        'parent_id' => $parentAccountTypeId
                    ])
                    ->withHttpStatus(500);
            }

            return $instance;
        } catch (Throwable $e) {
            if ($e instanceof LunaException) {
                throw $e;
            }
            throw LunaException::create($e)
                ->withDisplayMessage('创建账户类型实例时发生错误')
                ->withData(['name' => $name])
                ->withHttpStatus(500);
        }
    }

    /**
     * 创建账户类型
     *
     * 这是创建新账户类型的主要方法。该方法会：
     * 1. 验证处理器是否存在
     * 2. 创建账户类型记录
     * 3. 为所有已绑定的用户创建对应的账户
     * 4. 处理父子账户关系
     * 5. 清理相关缓存
     *
     * 使用示例：
     * ```php
     * // 创建简单的账户类型
     * $balanceType = $lunaAssetsAccount->createAccountType(
     *     'balance',
     *     'default_handler',
     *     '余额账户',
     *     '用户主要余额账户'
     * );
     * 
     * // 创建有父级的账户类型
     * $subBalance = $lunaAssetsAccount->createAccountType(
     *     'sub_balance',
     *     'default_handler',
     *     '子余额账户',
     *     '子账户',
     *     null,
     *     $balanceType
     * );
     * ```
     *
     * @param string $name 账户类型名称，必须唯一
     * @param string|int $handler 处理器名称或 ID
     * @param string|null $displayName 显示名称，如果为 null 则使用 name
     * @param string|null $description 描述信息
     * @param Repository|null $config 配置对象，如果为 null 则使用默认配置
     * @param string|int|AssetsAccountType|null $parent 父账户类型
     * @return AssetsAccountType 创建的账户类型实例
     * @throws LunaException 当处理器不存在或父账户类型不存在时抛出
     */
    public function createAccountType(
        string $name,
        string|int $handler,
        ?string $displayName = null,
        ?string $description = '',
        ?Repository $config = null,
        string|int|AssetsAccountType|null $parent = null,
    ): AssetsAccountType {
        // 创建请求对象并委托给新方法
        $request = new AccountTypeCreationRequest(
            name: $name,
            handler: $handler,
            displayName: $displayName,
            description: $description,
            config: $config,
            parent: $parent
        );

        return $this->createAccountTypeFromRequest($request);
    }

    /**
     * 使用请求对象创建账户类型
     *
     * 这是推荐的创建账户类型的方法，使用参数对象模式提供更好的可读性和扩展性。
     *
     * 使用示例：
     * ```php
     * // 创建简单的账户类型
     * $request = new AccountTypeCreationRequest(
     *     name: 'balance',
     *     handler: 'default_handler',
     *     displayName: '余额账户',
     *     description: '用户主要余额账户'
     * );
     * $balanceType = $lunaAssetsAccount->createAccountTypeFromRequest($request);
     * 
     * // 创建有父级的账户类型
     * $subRequest = new AccountTypeCreationRequest(
     *     name: 'sub_balance',
     *     handler: 'default_handler',
     *     displayName: '子余额账户',
     *     description: '子账户',
     *     parent: $balanceType
     * );
     * $subBalance = $lunaAssetsAccount->createAccountTypeFromRequest($subRequest);
     * ```
     *
     * @param AccountTypeCreationRequest $request 账户类型创建请求参数
     * @return AssetsAccountType 创建的账户类型实例
     * @throws LunaException 当处理器不存在或父账户类型不存在时抛出
     */
    public function createAccountTypeFromRequest(AccountTypeCreationRequest $request): AssetsAccountType {
        // 验证请求参数
        $this->validateAccountTypeCreationRequest($request);

        // 验证处理器是否存在
        $this->validateHandler($request->handler);

        // 定义核心处理逻辑
        $processing = function () use ($request) {
            // 创建账户类型实例
            $instance = $this->createAccountTypeInstance(
                $request->name,
                $request->handler,
                $request->getEffectiveDisplayName(),
                $request->getEffectiveDescription(),
                $request->getEffectiveConfig(),
                $request->parent
            );

            $parentAccountTypeId = $instance->parent_id;

            // 为所有已绑定的用户创建对应的账户
            foreach ($this->configure->bindings as $binding) {
                $this->createAccountsForBinding($binding, $instance, $parentAccountTypeId);
            }

            return $instance;
        };

        // 清理缓存
        $this->cache->forget('assets-account:types');

        return $this->executeInTransactionIfNeeded($processing);
    }

    /**
     * 获取所有账户类型（不使用缓存）
     *
     * 直接从数据库查询所有账户类型，包括父子关系信息。
     * 这个方法不使用缓存，适用于需要最新数据的场景。
     *
     * @return Collection<AssetsAccountType> 账户类型集合
     */
    public function getAllAccountTypesWithoutCache(): Collection
    {
        return collect(
            $this->configure->accountTypeModel::query()
                ->with(['parent', 'children'])
                ->orderBy('parent_id')
                ->get()
                ->all()
        );
    }

    /**
     * 获取所有账户类型
     *
     * 获取所有账户类型的数据，默认使用缓存提高性能。
     * 缓存会在创建新账户类型时自动清理。
     *
     * @param bool $withoutCache 是否跳过缓存，默认为 false
     * @return Collection<AssetsAccountType> 账户类型集合
     */
    public function getAllAccountTypes(bool $withoutCache = false): Collection
    {
        if ($withoutCache) {
            return $this->getAllAccountTypesWithoutCache();
        }

        return collect($this->cache->rememberForever('assets-account:types', function () {
            return $this->getAllAccountTypesWithoutCache()->all();
        }));
    }

    /**
     * 创建账户实例
     *
     * 这是一个内部方法，用于创建具体的资产账户实例。
     * 每个账户都属于特定的用户和账户类型。
     *
     * @param int $ownerType 账户所有者类型 ID
     * @param int $ownerId 账户所有者 ID
     * @param int $accountTypeId 账户类型 ID
     * @param int|null $parentId 父账户 ID，如果为 null 则设为 0
     * @return AssetsAccount 创建的账户实例
     */
    protected function createAccountInstance(
        int $ownerType,
        int $ownerId,
        int $accountTypeId,
        ?int $parentId = null
    ): AssetsAccount {
        /** @var AssetsAccount $instance */
        $instance = new ($this->configure->accountModel)();
        $instance->owner_id = $ownerId;
        $instance->owner_type = $ownerType;
        $instance->account_type_id = $accountTypeId;
        $instance->parent_id = $parentId ?? 0;
        $instance->save();

        return $instance;
    }

    /**
     * 为会话持有者创建账户
     *
     * 这个方法为指定的用户创建所有已定义的账户类型对应的账户。
     * 它会遍历所有账户类型，为每个类型创建对应的账户实例。
     *
     * 操作特点：
     * - 自动处理父子账户关系
     * - 在事务中执行，确保数据一致性
     * - 支持嵌套事务场景
     *
     * @param SessionHolder $owner 会话持有者，代表需要创建账户的用户
     * @return void
     */
    public function createOwnerAccount(SessionHolder $owner): void
    {
        $processing = function () use ($owner) {
            $types = $this->getAllAccountTypesWithoutCache();

            $types->each(function (AssetsAccountType $accountType) use ($owner) {
                Model::unguarded(function () use ($accountType, $owner) {
                    $parentAccount = null;
                    if ($accountType->parent) {
                        $parentAccount = $this->configure->accountModel::query()
                            ->where('owner_id', $owner->getOperatorId())
                            ->where('owner_type', $owner->getOperatorType())
                            ->where('account_type_id', $accountType->parent->id)
                            ->first();
                    }

                    $this->createAccountInstance(
                        $owner->getOperatorType(),
                        $owner->getOperatorId(),
                        $accountType->id,
                        $parentAccount?->id
                    );
                });
            });
        };

        // 重要操作，需要判断是否在事务中
        if (DB::connection()->transactionLevel()) {
            $processing();
        } else {
            DB::transaction(function () use ($processing) {
                $processing();
            });
        }
    }

    /**
     * 获取所有资产账户类型的概要信息
     *
     * 返回所有账户类型的简化信息，主要用于前端展示。
     * 只包含必要的字段，减少数据传输量。
     *
     * @return array 账户类型概要信息数组
     */
    public function accountTypes(): array
    {
        return $this->getAllAccountTypes()->map(function (AssetsAccountType $accountType) {
            return [
                'id' => $accountType->id,
                'name' => $accountType->name,
                'display_name' => $accountType->display_name,
                'description' => $accountType->description,
            ];
        })->values()->toArray();
    }

    /**
     * 获取会话持有者的指定账户
     *
     * 根据账户类型名称或 ID 获取指定用户的账户信息。
     * 支持预加载子账户信息。
     *
     * @param SessionHolder $owner 会话持有者
     * @param string|int $account 账户类型名称或 ID
     * @param bool $withChildren 是否包含子账户信息，默认为 false
     * @return AssetsAccount 账户实例
     * @throws LunaException 当账户不存在或参数无效时抛出
     */
    public function ownerAccount(SessionHolder $owner, string|int $account, bool $withChildren = false): AssetsAccount
    {
        try {
            // 参数验证
            if (is_string($account) && empty(trim($account))) {
                throw LunaException::create('Account type name cannot be empty')
                    ->withDisplayMessage('账户类型名称不能为空')
                    ->withHttpStatus(400);
            }

            if (is_int($account) && $account <= 0) {
                throw LunaException::create('Account type ID must be positive')
                    ->withDisplayMessage('账户类型 ID 必须为正数')
                    ->withHttpStatus(400);
            }

            $accountTypeId = is_string($account) ? hash_code($account) : $account;
            $query = $this->configure->accountModel::query();

            if ($withChildren) {
                $query->with('children');
            }

            $result = $query
                ->where('owner_id', $owner->getOperatorId())
                ->where('owner_type', $owner->getOperatorType())
                ->where('account_type_id', $accountTypeId)
                ->first();

            if (!$result) {
                throw LunaException::create('Account not found')
                    ->withDisplayMessage('账户不存在')
                    ->withData([
                        'owner_id' => $owner->getOperatorId(),
                        'owner_type' => $owner->getOperatorType(),
                        'account_type' => $account,
                        'account_type_id' => $accountTypeId
                    ])
                    ->withHttpStatus(404);
            }

            return $result;
        } catch (Throwable $e) {
            if ($e instanceof LunaException) {
                throw $e;
            }
            throw LunaException::create($e)
                ->withDisplayMessage('获取账户信息时发生错误')
                ->withData(['account_type' => $account])
                ->withHttpStatus(500);
        }
    }

    /**
     * 获取会话持有者的所有主账户
     *
     * 获取指定用户的所有主账户（parent_id = 0 的账户）。
     * 主账户是没有父账户的顶级账户。
     *
     * @param SessionHolder $owner 会话持有者
     * @param bool $withChildren 是否包含子账户信息，默认为 false
     * @return Collection<AssetsAccount> 主账户集合
     */
    public function ownerMainAccounts(SessionHolder $owner, bool $withChildren = false): Collection
    {
        $query = $this->configure->accountModel::query();

        if ($withChildren) {
            $query->with('children');
        }

        return collect(
            $query
                ->where('owner_id', $owner->getOperatorId())
                ->where('owner_type', $owner->getOperatorType())
                ->where('parent_id', 0)
                ->get()
                ->all()
        );
    }

    /**
     * 创建资产账户操作对象
     *
     * 创建一个账户操作对象，用于执行账户相关的操作，如余额更新、转账等。
     * 该对象提供了链式调用的接口，方便进行复杂的账户操作。
     *
     * @return AccountOperations 账户操作对象实例
     * @throws BindingResolutionException 当服务容器无法解析时抛出
     */
    public function createAccountOperation(): AccountOperations
    {
        return app()->make(AccountOperations::class, ['configure' => $this->configure]);
    }

    /**
     * 验证账户类型创建请求参数
     *
     * @param AccountTypeCreationRequest $request 创建请求参数
     * @return void
     * @throws LunaException 当参数无效时抛出
     */
    private function validateAccountTypeCreationRequest(AccountTypeCreationRequest $request): void
    {
        if (empty(trim($request->name))) {
            throw LunaException::create('Account type name cannot be empty')
                ->withDisplayMessage('账户类型名称不能为空')
                ->withHttpStatus(400);
        }

        if (is_string($request->handler) && empty(trim($request->handler))) {
            throw LunaException::create('Handler name cannot be empty')
                ->withDisplayMessage('处理器名称不能为空')
                ->withHttpStatus(400);
        }
    }

    /**
     * 验证处理器是否存在
     *
     * @param string|int $handler 处理器名称或 ID
     * @return void
     * @throws LunaException 当处理器不存在或验证失败时抛出
     */
    private function validateHandler(string|int $handler): void
    {
        try {
            if (!$this->handler->existsEntityHandler($handler)) {
                throw LunaException::create('Account handler not defined')
                    ->withDisplayMessage('账户处理器未定义')
                    ->withData(['handler' => $handler])
                    ->withHttpStatus(404);
            }
        } catch (Throwable $e) {
            if ($e instanceof LunaException) {
                throw $e;
            }
            throw LunaException::create($e)
                ->withDisplayMessage('验证处理器时发生错误')
                ->withData(['handler' => $handler])
                ->withHttpStatus(500);
        }
    }

    /**
     * 为指定的绑定创建账户
     *
     * @param SessionHolderBinding $binding 绑定配置
     * @param AssetsAccountType $instance 账户类型实例
     * @param int $parentAccountTypeId 父账户类型 ID
     * @return void
     */
    private function createAccountsForBinding(SessionHolderBinding $binding, AssetsAccountType $instance, int $parentAccountTypeId): void
    {
        // 构建查询
        if ($binding->table) {
            if ($binding->tableIsModelClass) {
                $query = $binding->table::query();
            } else {
                $query = DB::table($binding->table);
            }
        } else {
            $query = $binding->owner::query();
        }

        $ownerType = (new $binding->owner)->getOperatorType();

        // 根据是否有父账户构建不同的查询
        if ($parentAccountTypeId) {
            $this->buildQueryWithParentAccount($query, $binding, $ownerType, $instance, $parentAccountTypeId);
        } else {
            $this->buildQueryWithoutParentAccount($query, $binding, $ownerType, $instance);
        }
    }

    /**
     * 构建包含父账户的查询
     *
     * @param QueryBuilder|EloquentBuilder $query 查询构建器
     * @param SessionHolderBinding $binding 绑定配置
     * @param int $ownerType 所有者类型
     * @param AssetsAccountType $instance 账户类型实例
     * @param int $parentAccountTypeId 父账户类型 ID
     * @return void
     */
    private function buildQueryWithParentAccount(QueryBuilder|EloquentBuilder $query, SessionHolderBinding $binding, int $ownerType, AssetsAccountType $instance, int $parentAccountTypeId): void
    {
        $columns = ['owner_id', 'owner_type', 'account_type_id', 'parent_id', 'created_at', 'updated_at'];
        $query
            ->selectRaw(
                implode(',', [
                    $binding->keyName,
                    '?',
                    '?',
                    'parent_id',
                    'now() created_at',
                    'now() updated_at',
                ]),
                [$ownerType, $instance->id],
            )
            ->leftJoinSub($this->configure->accountModel::query()
                ->select(['id as parent_id', 'owner_id'])
                ->where('owner_type', $ownerType)
                ->where('account_type_id', $parentAccountTypeId), 'parent', 'parent.owner_id', '=',
                sprintf('%s.%s', $binding->tableName, $binding->keyName));

        $this->configure->accountModel::query()->insertUsing($columns, $query);
    }

    /**
     * 构建不包含父账户的查询
     *
     * @param QueryBuilder|EloquentBuilder $query 查询构建器
     * @param SessionHolderBinding $binding 绑定配置
     * @param int $ownerType 所有者类型
     * @param AssetsAccountType $instance 账户类型实例
     * @return void
     */
    private function buildQueryWithoutParentAccount(QueryBuilder|EloquentBuilder $query, SessionHolderBinding $binding, int $ownerType, AssetsAccountType $instance): void
    {
        $columns = ['owner_id', 'owner_type', 'account_type_id', 'created_at', 'updated_at'];
        $query
            ->selectRaw(
                implode(',', [
                    $binding->keyName,
                    '?',
                    '?',
                    'now() created_at',
                    'now() updated_at',
                ]),
                [$ownerType, $instance->id],
            );

        $this->configure->accountModel::query()->insertUsing($columns, $query);
    }

    /**
     * 在事务中执行处理逻辑（如果需要）
     *
     * @param callable $processing 处理逻辑
     * @return mixed 处理结果
     */
    private function executeInTransactionIfNeeded(callable $processing): mixed
    {
        if (DB::connection()->transactionLevel()) {
            return $processing();
        } else {
            return DB::transaction(function () use ($processing) {
                return $processing();
            });
        }
    }
}
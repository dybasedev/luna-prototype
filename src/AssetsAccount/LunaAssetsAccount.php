<?php

namespace Dybasedev\LunaPrototype\AssetsAccount;

use Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccount;
use Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccountType;
use Dybasedev\LunaPrototype\Foundation\Configuration\Repository;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\LunaModule;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 资产账户管理对象
 *
 * 可以通过这个对象对资产账户进行管理
 */
class LunaAssetsAccount extends LunaModule
{
    public function __construct(
        protected(set) LunaAssetsAccountConfigure $configure,
        protected(set) LunaHandler $handler,
        protected Cache $cache
    ) {

    }

    public function createAccountType(
        string $name,
        string|int $handler,
        ?string $displayName = null,
        ?string $description = '',
        ?Repository $config = null,
        string|int|AssetsAccountType|null $parent = null,
    ) {
        if (!$this->handler->existsEntityHandler($handler)) {
            throw LunaException::create('Account handler not defined.');
        }

        if (!$config) {
            $config = new AccountHandlerConfigurationRepository([]);
        }

        $processing = function () use ($handler, $name, $config, $description, $displayName, $parent) {
            $parentAccountTypeId = 0;
            if ($parent) {
                if ($parent instanceof AssetsAccountType) {
                    $parentAccountTypeId = $parent->id;
                } else {
                    $parentAccountTypeId = is_string($parent) ? hash_code($parent) : $parent;
                    // 检查父级账户类型是否存在
                    if (!$this->getAllAccountTypes()->where('id', $parentAccountTypeId)->count()) {
                        throw LunaException::create('Parent account type not exists.');
                    }
                }
            }

            /** @var AssetsAccountType $instance */
            $instance = new ($this->configure->accountTypeModel)();
            $instance->forceFill([
                'parent_id' => $parentAccountTypeId,
                'name' => $name,
                'display_name' => $displayName ?? $name,
                'description' => $description ?? '',
                'handler_id' => is_string($handler) ? hash_code($handler) : $handler,
                'config' => $config->all(),
            ]);


            if (!$instance->save()) {
                throw new RuntimeException('Save entity failed');
            }

            // 针对绑定账户的对象进行创建
            foreach ($this->configure->bindings as $binding) {
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

                // 判断是否存在父账户
                if ($parentAccountTypeId) {
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
                } else {
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
                }


                $this->configure->accountModel::query()->insertUsing($columns, $query);
            }

            return $instance;
        };

        $this->cache->forget('assets-account:types');

        // 重要操作，需要判断是否在事务中
        if (DB::connection()->transactionLevel()) {
            return $processing();
        } else {
            return DB::transaction(function () use ($processing) {
                return $processing();
            });
        }
    }

    public function getAllAccountTypes(): Collection
    {
        return collect($this->cache->rememberForever('assets-account:types', function () {
            return $this->configure->accountTypeModel::query()
                ->with(['parent', 'children'])
                ->orderBy('parent_id')
                ->get()
                ->all();
        }));
    }

    public function createOwnerAccount(SessionHolder $owner): void
    {
        $processing = function () use ($owner) {
            $types = $this->getAllAccountTypes();

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

                    $this->configure->accountModel::query()->create([
                        'owner_id' => $owner->getOperatorId(),
                        'owner_type' => $owner->getOperatorType(),
                        'account_type_id' => $accountType->id,
                        'parent_id' => $parentAccount?->id ?? 0,
                    ]);
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
     * @return array
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

    public function ownerAccount(SessionHolder $owner, string|int $account): AssetsAccount
    {
        $account = is_string($account) ? hash_code($account) : $account;
        return $this->configure->accountModel::query()
            ->where('owner_id', $owner->getOperatorId())
            ->where('owner_type', $owner->getOperatorType())
            ->where('account_type_id', $account)
            ->first();
    }

    /**
     * @throws BindingResolutionException
     */
    public function createAccountOperation(): AccountOperations
    {
        return app()->make(AccountOperations::class, ['configure' => $this->configure]);
    }
}
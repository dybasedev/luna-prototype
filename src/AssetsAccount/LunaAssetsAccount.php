<?php

namespace Dybasedev\LunaPrototype\AssetsAccount;

use Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccountType;
use Dybasedev\LunaPrototype\Foundation\Configuration\Repository;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\LunaModule;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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
    ) {
        if (!$this->handler->existsEntityHandler($handler)) {
            throw LunaException::create('Account handler not defined.');
        }

        if (!$config) {
            $config = new AccountHandlerConfigurationRepository([]);
        }

        $processing = function () use ($handler, $name, $config, $description, $displayName) {
            /** @var AssetsAccountType $instance */
            $instance = new ($this->configure->accountTypeModel)();
            $instance->forceFill([
                'name' => $name,
                'display_name' => $displayName ?? $name,
                'description' => $description ?? '',
                'handler_id' => is_string($handler) ? hash_code($handler) : $handler,
                'config' => $config->everything(),
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

                $this->configure->accountModel::query()->insertUsing(
                    ['owner_id', 'owner_type', 'account_type_id', 'created_at', 'updated_at'],
                    $query->selectRaw(
                        implode(',', [
                            $binding->keyName,
                            '?',
                            '?',
                            'now() created_at',
                            'now() updated_at',
                        ]),
                        [$ownerType, $instance->id],
                    )
                );
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
            return $this->configure->accountTypeModel::query()->get()->all();
        }));
    }

    public function createOwnerAccount(SessionHolder $owner): void
    {
        $processing = function () use ($owner) {
            $types = $this->getAllAccountTypes();

            $types->each(function (AssetsAccountType $accountType) use ($owner) {
                Model::unguarded(function () use ($accountType, $owner) {
                    $this->configure->accountModel::query()->create([
                        'owner_id' => $owner->getOperatorId(),
                        'owner_type' => $owner->getOperatorType(),
                        'account_type_id' => $accountType->id,
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
}
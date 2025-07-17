<?php

namespace Dybasedev\LunaPrototype\AssetsAccount;

use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Log;
use Random\RandomException;
use Throwable;


class AccountOperations
{
    protected(set) array $operations = [];

    /**
     * @var AccountBalanceTypeEnum[]
     */
    protected(set) array $operationAccountBalanceTypes = [];

    protected(set) int $processId;

    /**
     * @var string
     */
    protected string $temporaryTableName = 'luna_assets_process_account_change_logs';

    const array COLUMNS = [
        'owner_id',
        'owner_type',
        'account_id',
        'account_type_id',
        'change_type',
        'change_value',
        'before_value',
        'event_id',
        'payload',
        'created_at',
        'updated_at',
        'process_id',
    ];

    /**
     * 账户操作构造函数
     *
     * @param LunaAssetsAccountConfigure $configure 资产账户配置
     * @param ConnectionInterface $connection 数据库连接
     * @throws LunaException 当生成处理ID失败时抛出
     */
    public function __construct(
        protected LunaAssetsAccountConfigure $configure,
        protected ConnectionInterface $connection
    ) {
        try {
            $this->processId = random_int(10000000, 99999999);
        } catch (RandomException $e) {
            throw LunaException::create($e)
                ->withDisplayMessage('生成处理 ID 失败')
                ->withHttpStatus(500);
        }
    }

    /**
     * 添加账户操作
     *
     * @param AccountOperationBuilder $builder 账户操作构建器
     * @return static 当前实例，支持链式调用
     * @throws LunaException 当操作构建失败时抛出
     */
    public function operation(AccountOperationBuilder $builder): static
    {
        try {
            $operations = $builder->build();

            if (empty($operations)) {
                throw LunaException::create('No operations to add')
                    ->withDisplayMessage('没有可添加的操作')
                    ->withHttpStatus(400);
            }

            foreach ($operations as $operation) {
                // 验证操作数据完整性
                if (!isset($operation['balance_type']) || !isset($operation['amount'])) {
                    throw LunaException::create('Invalid operation data')
                        ->withDisplayMessage('操作数据无效')
                        ->withData(['operation' => $operation])
                        ->withHttpStatus(400);
                }

                if (!in_array($operation['balance_type'], $this->operationAccountBalanceTypes)) {
                    $this->operationAccountBalanceTypes[] = $operation['balance_type'];
                }
                $this->operations[] = $operation;
            }

            return $this;
        } catch (Throwable $e) {
            if ($e instanceof LunaException) {
                throw $e;
            }
            throw LunaException::create($e)
                ->withDisplayMessage('添加账户操作时发生错误')
                ->withHttpStatus(500);
        }
    }

    /**
     * @param array{
     *      account_id: int,
     *      balance_type: AccountBalanceTypeEnum,
     *      amount: string,
     *      event_id: int,
     *      payload: array
     *  } $payload
     * @return QueryBuilder
     */
    protected function createAccountChangeQueryFromPayload(array $payload): QueryBuilder
    {
        return $this->connection->table('luna_assets_accounts')
            ->selectRaw(
                implode(',', [
                    'owner_id',
                    'owner_type',
                    'id as account_id',
                    'account_type_id',
                    '? as change_type',
                    '? as change_value',
                    $payload['balance_type']->getFieldName() . ' as before_value',
                    '? as event_id',
                    '? as payload',
                    'current_timestamp() as created_at',
                    'current_timestamp() as updated_at',
                    '? as process_id'
                ]),
                [
                    $payload['balance_type']->value,
                    $payload['amount'],
                    $payload['event_id'],
                    json_encode($payload['payload'], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS),
                    $this->processId,
                ]
            )
            ->where('id', $payload['account_id']);
    }

    protected function insertAccountChangesIntoTemporaryTable(): void
    {
        $operations = $this->operations;
        $query = $this->createAccountChangeQueryFromPayload(array_pop($operations));

        foreach ($operations as $operation) {
            $query->unionAll($this->createAccountChangeQueryFromPayload($operation));
        }

        $this->connection->table($this->temporaryTableName)->insertUsing(static::COLUMNS, $query);
    }

    /**
     * @throws Throwable
     */
    /**
     * 提交账户操作
     *
     * 执行所有已添加的账户操作，在事务中确保数据一致性。
     *
     * @param bool $allowOverdraft 是否允许透支，默认为 false
     * @return void
     * @throws LunaException 当操作失败时抛出
     */
    public function submit(bool $allowOverdraft = false): void
    {
        if (!count($this->operations)) {
            Log::info('No operations to submit');
            return;
        }

        try {
            $process = function () use ($allowOverdraft) {
            // 变更过程写入中间表
            $this->insertAccountChangesIntoTemporaryTable();

            // 同步到正式表
            $columns = static::COLUMNS;
            array_pop($columns);

            $this->configure->accountChangeLogModel::query()->insertUsing(
                $columns,
                $this->connection->table($this->temporaryTableName)->select($columns)->where('process_id', $this->processId)
            );

            // 按照余额类型依次变更
            foreach ($this->operationAccountBalanceTypes as $accountBalanceType) {
                // 获取变动记录
                $stage = $this->connection
                    ->table($this->temporaryTableName)
                    ->select([
                        'account_id',
                        $this->connection->raw('sum(change_value) as changing')
                    ])
                    ->where('change_type', $accountBalanceType->value)
                    ->where('process_id', $this->processId)
                    ->groupBy('account_id');

                $nextCheckStage = $stage->clone();

                // 根据变动记录更新余额
                $count = $this->configure->accountModel::query()
                    ->joinSub($stage, 'stage', function (JoinClause $join) {
                        $join->on('luna_assets_accounts.id', '=', 'stage.account_id');
                    })
                    ->whereColumn('luna_assets_accounts.id', 'stage.account_id')
                    ->when(!$allowOverdraft, function (\Illuminate\Database\Eloquent\Builder $query) use ($accountBalanceType) {
                        $query->whereRaw("luna_assets_accounts.{$accountBalanceType->getFieldName()} + stage.changing >= 0");
                    })
                    ->update([
                        $accountBalanceType->getFieldName() => $this->connection->raw(
                            "luna_assets_accounts.{$accountBalanceType->getFieldName()} + stage.changing"
                        ),
                        'updated_at' => $this->connection->raw('current_timestamp()')
                    ]);

                // 如果不允许透支，则需要检查是否透支
                if (!$allowOverdraft) {
                    if (count($this->operations) === 1 && $count === 0) {
                        throw LunaException::create('账户余额扣减失败：余额不足');
                    } else {
                        // 需要取得账户变更数量
                        $needMatched = $this->connection->table($nextCheckStage, 'stage')->count();
                        Log::debug('context', ['count' => $count, 'needmatch' => $needMatched]);

                        if ($count !== $needMatched) {
                            throw LunaException::create('账户余额扣减失败：余额不足');
                        }
                    }
                }
            }
        };

            // 执行处理逻辑
            if ($this->connection->transactionLevel()) {
                $process();
            } else {
                $this->connection->transaction($process);
            }

            // 重置处理状态
            $this->operations = [];
            $this->operationAccountBalanceTypes = [];

            Log::info('Account operations submitted successfully', [
                'process_id' => $this->processId,
                'operations_count' => count($this->operations)
            ]);

        } catch (Throwable $e) {
            // 确保清理临时数据
            try {
                $this->connection->table($this->temporaryTableName)
                    ->where('process_id', $this->processId)
                    ->delete();
            } catch (Throwable $cleanupError) {
                Log::error('Failed to cleanup temporary data', [
                    'process_id' => $this->processId,
                    'error' => $cleanupError->getMessage()
                ]);
            }

            if ($e instanceof LunaException) {
                throw $e;
            }

            throw LunaException::create($e)
                ->withDisplayMessage('提交账户操作时发生错误')
                ->withData([
                    'process_id' => $this->processId,
                    'operations_count' => count($this->operations),
                    'allow_overdraft' => $allowOverdraft
                ])
                ->withHttpStatus(500);
        } finally {
            // 最终清理中间过程数据
            try {
                $this->connection->table($this->temporaryTableName)
                    ->where('process_id', $this->processId)
                    ->delete();
            } catch (Throwable $cleanupError) {
                Log::warning('Final cleanup failed', [
                    'process_id' => $this->processId,
                    'error' => $cleanupError->getMessage()
                ]);
            }
        }
    }

    /**
     * 执行账户操作的核心逻辑
     *
     * @param bool $allowOverdraft 是否允许透支
     * @return void
     * @throws LunaException
     */
    private function executeAccountOperations(bool $allowOverdraft): void
    {
        // 变更过程写入中间表
        $this->insertAccountChangesIntoTemporaryTable();

        // 同步到正式表
        $this->syncChangesToLogTable();

        // 按照余额类型依次变更
        foreach ($this->operationAccountBalanceTypes as $accountBalanceType) {
            $this->processBalanceTypeOperation($accountBalanceType, $allowOverdraft);
        }
    }

    /**
     * 同步变更记录到正式日志表
     *
     * @return void
     */
    private function syncChangesToLogTable(): void
    {
        $columns = static::COLUMNS;
        array_pop($columns);

        $this->configure->accountChangeLogModel::query()->insertUsing(
            $columns,
            $this->connection->table($this->temporaryTableName)
                ->select($columns)
                ->where('process_id', $this->processId)
        );
    }

    /**
     * 处理特定余额类型的操作
     *
     * @param AccountBalanceTypeEnum $accountBalanceType 账户余额类型
     * @param bool $allowOverdraft 是否允许透支
     * @return void
     * @throws LunaException
     */
    private function processBalanceTypeOperation(AccountBalanceTypeEnum $accountBalanceType, bool $allowOverdraft): void
    {
        // 获取变动记录
        $stage = $this->buildChangeStage($accountBalanceType);
        $nextCheckStage = $stage->clone();

        // 更新账户余额
        $updatedCount = $this->updateAccountBalance($stage, $accountBalanceType, $allowOverdraft);

        // 验证余额更新结果
        if (!$allowOverdraft) {
            $this->validateBalanceUpdate($updatedCount, $nextCheckStage);
        }
    }

    /**
     * 构建变动记录查询
     *
     * @param AccountBalanceTypeEnum $accountBalanceType 账户余额类型
     * @return \Illuminate\Database\Query\Builder
     */
    private function buildChangeStage(AccountBalanceTypeEnum $accountBalanceType): \Illuminate\Database\Query\Builder
    {
        return $this->connection
            ->table($this->temporaryTableName)
            ->select([
                'account_id',
                $this->connection->raw('sum(change_value) as changing')
            ])
            ->where('change_type', $accountBalanceType->value)
            ->where('process_id', $this->processId)
            ->groupBy('account_id');
    }

    /**
     * 更新账户余额
     *
     * @param \Illuminate\Database\Query\Builder $stage 变动记录查询
     * @param AccountBalanceTypeEnum $accountBalanceType 账户余额类型
     * @param bool $allowOverdraft 是否允许透支
     * @return int 更新的记录数
     */
    private function updateAccountBalance(\Illuminate\Database\Query\Builder $stage, AccountBalanceTypeEnum $accountBalanceType, bool $allowOverdraft): int
    {
        return $this->configure->accountModel::query()
            ->joinSub($stage, 'stage', function (JoinClause $join) {
                $join->on('luna_assets_accounts.id', '=', 'stage.account_id');
            })
            ->whereColumn('luna_assets_accounts.id', 'stage.account_id')
            ->when(!$allowOverdraft, function (\Illuminate\Database\Eloquent\Builder $query) use ($accountBalanceType) {
                $query->whereRaw("luna_assets_accounts.{$accountBalanceType->getFieldName()} + stage.changing >= 0");
            })
            ->update([
                $accountBalanceType->getFieldName() => $this->connection->raw(
                    "luna_assets_accounts.{$accountBalanceType->getFieldName()} + stage.changing"
                ),
                'updated_at' => $this->connection->raw('current_timestamp()')
            ]);
    }

    /**
     * 验证余额更新结果
     *
     * @param int $updatedCount 实际更新的记录数
     * @param \Illuminate\Database\Query\Builder $expectedStage 期望的变动记录查询
     * @return void
     * @throws LunaException
     */
    private function validateBalanceUpdate(int $updatedCount, \Illuminate\Database\Query\Builder $expectedStage): void
    {
        if (count($this->operations) === 1 && $updatedCount === 0) {
            throw LunaException::create('账户余额扣减失败：余额不足')
                ->withDisplayMessage('账户余额不足，无法完成操作')
                ->withHttpStatus(400);
        }

        if (count($this->operations) > 1) {
            $expectedCount = $this->connection->table($expectedStage, 'stage')->count();
            
            Log::debug('Balance update validation', [
                'updated_count' => $updatedCount,
                'expected_count' => $expectedCount,
                'operations_count' => count($this->operations)
            ]);

            if ($updatedCount !== $expectedCount) {
                throw LunaException::create('账户余额扣减失败：余额不足')
                    ->withDisplayMessage('部分账户余额不足，操作已回滚')
                    ->withData([
                        'updated_count' => $updatedCount,
                        'expected_count' => $expectedCount
                    ])
                    ->withHttpStatus(400);
            }
        }
    }
}
<?php

namespace Dybasedev\LunaPrototype\AssetsAccount;

use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Illuminate\Contracts\Database\Query\Builder;
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
     * @throws RandomException
     */
    public function __construct(
        protected LunaAssetsAccountConfigure $configure,
        protected ConnectionInterface $connection
    ) {
        $this->processId = hash_code(base64_encode(random_bytes(random_int(4, 12))));
    }

    public function operation(AccountOperationBuilder $builder): static
    {
        $operations = $builder->build();

        foreach ($operations as $operation) {
            if (!in_array($operation['balance_type'], $this->operationAccountBalanceTypes)) {
                $this->operationAccountBalanceTypes[] = $operation['balance_type'];
            }
            $this->operations[] = $operation;
        }

        return $this;
    }

    /**
     * @param array{
     *      account_id: int,
     *      balance_type: AccountBalanceTypeEnum,
     *      amount: string,
     *      event_id: int,
     *      payload: array
     *  } $payload
     * @return Builder
     */
    protected function createAccountChangeQueryFromPayload(array $payload): Builder
    {
        return $this->connection->table('luna_assets_accounts')
            ->selectRaw(
                implode(',', [
                    'owner_id',
                    'owner_type',
                    'id',
                    'account_type_id',
                    '? balance_type',
                    '? amount',
                    $payload['balance_type']->getFieldName(),
                    '? event_id',
                    '? payload',
                    'current_timestamp() as created_at',
                    'current_timestamp() as updated_at',
                    '? process_id'
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
    public function submit(bool $allowOverdraft = false): void
    {
        if (!count($this->operations)) {
            return;
        }

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
                    ->when(!$allowOverdraft, function (Builder $query) use ($accountBalanceType) {
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

        if ($this->connection->transactionLevel()) {
            $process();
        } else {
            $this->connection->transaction($process);
        }

        // 重置处理状态
        $this->operations = [];
        $this->operationAccountBalanceTypes = [];

        // 清空中间过程数据
        $this->connection->table($this->temporaryTableName)->where('process_id', $this->processId)->delete();
    }
}
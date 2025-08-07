<?php

namespace Dybasedev\LunaPrototype\DnW;

use Dybasedev\LunaPrototype\DnW\Models\DepositTransaction;
use Dybasedev\LunaPrototype\DnW\Models\WithdrawTransaction;
use Dybasedev\LunaPrototype\DnW\Models\DepositChannel;
use Dybasedev\LunaPrototype\DnW\Models\WithdrawChannel;
use Dybasedev\LunaPrototype\DnW\Models\DepositBinding;
use Dybasedev\LunaPrototype\DnW\Models\WithdrawBinding;
use Dybasedev\LunaPrototype\DnW\TransactionStatus;
use Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler;
use Dybasedev\LunaPrototype\Foundation\LunaModule;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 出入金服务主类
 */
class LunaDnW extends LunaModule
{

    /**
     * 构造函数
     */
    public function __construct(
        protected(set) LunaDnWConfigure $configure
    ) {
    }

    /**
     * 创建入金渠道
     */
    public function createDepositChannel(
        string $name,
        int|Handler $handler,
        array $config = [],
        array $metadata = [],
        bool $isActive = true,
        int $sort = 0
    ): DepositChannel {
        return DB::transaction(function () use ($name, $handler, $config, $metadata, $isActive, $sort) {
            $modelClass = $this->configure->depositChannelModel;
            
            $handlerId = $handler instanceof Handler ? $handler->id : $handler;
            
            $instance = new $modelClass();
            $instance->forceFill([
                'name' => $name,
                'handler_id' => $handlerId,
                'config' => $config,
                'metadata' => $metadata,
                'is_active' => $isActive,
                'sort' => $sort,
            ]);
            
            if (!$instance->save()) {
                throw LunaException::create('Failed to save deposit channel')
                    ->withDisplayMessage('保存入金渠道失败')
                    ->withHttpStatus(500);
            }
            
            return $instance;
        });
    }

    /**
     * 创建出金渠道
     */
    public function createWithdrawChannel(
        string $name,
        int|Handler $handler,
        array $config = [],
        array $metadata = [],
        bool $isActive = true,
        int $sort = 0
    ): WithdrawChannel {
        return DB::transaction(function () use ($name, $handler, $config, $metadata, $isActive, $sort) {
            $modelClass = $this->configure->withdrawChannelModel;
            
            $handlerId = $handler instanceof Handler ? $handler->id : $handler;
            
            $instance = new $modelClass();
            $instance->forceFill([
                'name' => $name,
                'handler_id' => $handlerId,
                'config' => $config,
                'metadata' => $metadata,
                'is_active' => $isActive,
                'sort' => $sort,
            ]);
            
            if (!$instance->save()) {
                throw LunaException::create('Failed to save withdraw channel')
                    ->withDisplayMessage('保存出金渠道失败')
                    ->withHttpStatus(500);
            }
            
            return $instance;
        });
    }

    /**
     * 创建入金交易
     */
    public function createDepositTransaction(
        Model $owner,
        DepositChannel|int|string $channel,
        float $amount,
        array $options = []
    ): DepositTransaction {
        $callback = function () use ($owner, $channel, $amount, $options) {
            // 解析渠道
            if (!($channel instanceof DepositChannel)) {
                $channelModel = $this->configure->depositChannelModel;
                $channel = $channelModel::findOrFail($channel);
            }

            // 验证渠道是否激活
            if (!$channel->is_active) {
                throw LunaException::create('Deposit channel is not active')
                    ->withDisplayMessage('入金渠道未激活')
                    ->withHttpStatus(400);
            }

            $modelClass = $this->configure->depositTransactionModel;
            
            $transaction = $modelClass::create([
                'channel_id' => $channel->id,
                'owner_id' => $owner->getKey(),
                'owner_type' => hash_code($owner->getMorphClass()),
                'amount' => $amount,
                'fee' => $options['fee'] ?? 0,
                'currency_id' => $options['currency_id'] ?? null,
                'external_id' => $options['external_id'] ?? null,
                'origin_id' => $options['origin_id'] ?? null,
                'origin_type' => isset($options['origin_type']) ? hash_code($options['origin_type']) : null,
                'extra_data' => $options['extra_data'] ?? null,
                'status' => TransactionStatus::Pending->getCode(),
            ]);

            return $transaction;
        };
        
        // In testing environment, don't use nested transactions
        if (app()->environment('testing') && DB::transactionLevel() > 0) {
            return $callback();
        }
        
        return DB::transaction($callback);
    }

    /**
     * 创建出金交易
     */
    public function createWithdrawTransaction(
        Model $owner,
        WithdrawChannel|int|string $channel,
        float $amount,
        array $options = []
    ): WithdrawTransaction {
        $callback = function () use ($owner, $channel, $amount, $options) {
            // 解析渠道
            if (!($channel instanceof WithdrawChannel)) {
                $channelModel = $this->configure->withdrawChannelModel;
                $channel = $channelModel::findOrFail($channel);
            }

            // 验证渠道是否激活
            if (!$channel->is_active) {
                throw LunaException::create('Withdraw channel is not active')
                    ->withDisplayMessage('出金渠道未激活')
                    ->withHttpStatus(400);
            }

            $modelClass = $this->configure->withdrawTransactionModel;
            
            $transaction = $modelClass::create([
                'channel_id' => $channel->id,
                'owner_id' => $owner->getKey(),
                'owner_type' => hash_code($owner->getMorphClass()),
                'amount' => $amount,
                'fee' => $options['fee'] ?? 0,
                'currency_id' => $options['currency_id'] ?? null,
                'external_id' => $options['external_id'] ?? null,
                'origin_id' => $options['origin_id'] ?? null,
                'origin_type' => isset($options['origin_type']) ? hash_code($options['origin_type']) : null,
                'extra_data' => $options['extra_data'] ?? null,
                'status' => TransactionStatus::Pending->getCode(),
            ]);

            // 检查是否需要审核
            if ($this->configure->enableWithdrawReview && $amount >= $this->configure->withdrawReviewThreshold) {
                $transaction->transitionTo(TransactionStatus::Reviewing);
            }

            return $transaction;
        };
        
        // In testing environment, don't use nested transactions
        if (app()->environment('testing') && DB::transactionLevel() > 0) {
            return $callback();
        }
        
        return DB::transaction($callback);
    }

    /**
     * 创建入金绑定
     */
    public function createDepositBinding(
        Model $owner,
        DepositChannel|int|string $channel,
        array $bindingInfo
    ): DepositBinding {
        $callback = function () use ($owner, $channel, $bindingInfo) {
            // 解析渠道
            if (!($channel instanceof DepositChannel)) {
                $channelModel = $this->configure->depositChannelModel;
                $channel = $channelModel::findOrFail($channel);
            }

            $modelClass = $this->configure->depositBindingModel;
            
            $binding = $modelClass::create([
                'channel_id' => $channel->id,
                'owner_id' => $owner->getKey(),
                'owner_type' => hash_code($owner->getMorphClass()),
                'channel' => $bindingInfo['channel'],
                'account' => $bindingInfo['account'],
                'account_name' => $bindingInfo['account_name'] ?? null,
                'channel_name' => $bindingInfo['channel_name'],
                'channel_provider' => $bindingInfo['channel_provider'],
                'extra_info' => $bindingInfo['extra_info'] ?? null,
                'metadata' => $bindingInfo['metadata'] ?? null,
                'sort' => $bindingInfo['sort'] ?? 0,
                'is_active' => true,
                'is_default' => false,
            ]);

            // 如果需要验证
            if ($this->configure->requireBindingVerification) {
                // 这里可以触发验证流程
            } else {
                $binding->verified_at = now();
                $binding->save();
            }

            return $binding;
        };
        
        // In testing environment, don't use nested transactions
        if (app()->environment('testing') && DB::transactionLevel() > 0) {
            return $callback();
        }
        
        return DB::transaction($callback);
    }

    /**
     * 创建出金绑定
     */
    public function createWithdrawBinding(
        Model $owner,
        WithdrawChannel|int|string $channel,
        array $bindingInfo
    ): WithdrawBinding {
        $callback = function () use ($owner, $channel, $bindingInfo) {
            // 解析渠道
            if (!($channel instanceof WithdrawChannel)) {
                $channelModel = $this->configure->withdrawChannelModel;
                $channel = $channelModel::findOrFail($channel);
            }

            // 验证账户信息
            if ($channel->handler) {
                $handler = $this->getWithdrawHandler($channel->handler->handler, $channel);
                if ($handler && !$handler->validateAccount($bindingInfo)) {
                    throw LunaException::create('Invalid account information')
                        ->withDisplayMessage('账户信息无效')
                        ->withHttpStatus(400);
                }
            }

            $modelClass = $this->configure->withdrawBindingModel;
            
            $binding = $modelClass::create([
                'channel_id' => $channel->id,
                'owner_id' => $owner->getKey(),
                'owner_type' => hash_code($owner->getMorphClass()),
                'channel' => $bindingInfo['channel'],
                'account' => $bindingInfo['account'],
                'account_name' => $bindingInfo['account_name'] ?? null,
                'channel_name' => $bindingInfo['channel_name'],
                'channel_provider' => $bindingInfo['channel_provider'],
                'extra_info' => $bindingInfo['extra_info'] ?? null,
                'metadata' => $bindingInfo['metadata'] ?? null,
                'sort' => $bindingInfo['sort'] ?? 0,
                'is_active' => true,
                'is_default' => false,
            ]);

            // 如果需要验证
            if ($this->configure->requireBindingVerification) {
                // 这里可以触发验证流程
            } else {
                $binding->verified_at = now();
                $binding->save();
            }

            return $binding;
        };
        
        // In testing environment, don't use nested transactions
        if (app()->environment('testing') && DB::transactionLevel() > 0) {
            return $callback();
        }
        
        return DB::transaction($callback);
    }

    /**
     * 查询入金渠道
     */
    public function queryDepositChannels(): Builder
    {
        $modelClass = $this->configure->depositChannelModel;
        return $modelClass::query();
    }

    /**
     * 查询出金渠道
     */
    public function queryWithdrawChannels(): Builder
    {
        $modelClass = $this->configure->withdrawChannelModel;
        return $modelClass::query();
    }

    /**
     * 查询入金交易
     */
    public function queryDepositTransactions(?Model $owner = null): Builder
    {
        $modelClass = $this->configure->depositTransactionModel;
        $query = $modelClass::query();
        
        if ($owner) {
            $query->where('owner_type', hash_code($owner->getMorphClass()))
                  ->where('owner_id', $owner->getKey());
        }
        
        return $query;
    }

    /**
     * 查询出金交易
     */
    public function queryWithdrawTransactions(?Model $owner = null): Builder
    {
        $modelClass = $this->configure->withdrawTransactionModel;
        $query = $modelClass::query();
        
        if ($owner) {
            $query->where('owner_type', hash_code($owner->getMorphClass()))
                  ->where('owner_id', $owner->getKey());
        }
        
        return $query;
    }

    /**
     * 查询入金绑定
     */
    public function queryDepositBindings(Model $owner): Builder
    {
        $modelClass = $this->configure->depositBindingModel;
        return $modelClass::query()
            ->where('owner_type', hash_code($owner->getMorphClass()))
            ->where('owner_id', $owner->getKey());
    }

    /**
     * 查询出金绑定
     */
    public function queryWithdrawBindings(Model $owner): Builder
    {
        $modelClass = $this->configure->withdrawBindingModel;
        return $modelClass::query()
            ->where('owner_type', hash_code($owner->getMorphClass()))
            ->where('owner_id', $owner->getKey());
    }

    /**
     * 获取入金处理器实例
     * @throws BindingResolutionException
     */
    public function getDepositHandler(string $handlerClass, ?DepositChannel $channel = null): ?Handlers\Contracts\DepositHandlerInterface
    {
        if (!class_exists($handlerClass)) {
            return null;
        }
        
        $handler = app()->make($handlerClass);
        
        if (!($handler instanceof Handlers\Contracts\DepositHandlerInterface)) {
            throw LunaException::create("Handler {$handlerClass} must implement DepositHandlerInterface")
                ->withDisplayMessage('处理器必须实现 DepositHandlerInterface 接口')
                ->withHttpStatus(500);
        }
        
        // 设置配置
        if ($channel && $channel->handler && method_exists($handler, 'withConfig')) {
            // Get the handler entity and its config
            $handlerEntity = $channel->handler;
            if ($handlerEntity instanceof \Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler) {
                $configData = $handlerEntity->getAttributeValue('config');
                if (is_array($configData) && !empty($configData)) {
                    $handler->withConfig($configData);
                }
            }
        }
        
        // 设置处理器ID
        if ($channel && $channel->handler && property_exists($handler, 'handlerId')) {
            $handler->handlerId = $channel->handler->id;
        }
        
        // 设置模型实例
        if ($channel && method_exists($handler, 'loadInstance')) {
            $handler->loadInstance($channel);
        }
        
        return $handler;
    }

    /**
     * 获取出金处理器实例
     */
    public function getWithdrawHandler(string $handlerClass, ?WithdrawChannel $channel = null): ?Handlers\Contracts\WithdrawHandlerInterface
    {
        if (!class_exists($handlerClass)) {
            return null;
        }
        
        $handler = app($handlerClass);
        
        if (!($handler instanceof Handlers\Contracts\WithdrawHandlerInterface)) {
            throw LunaException::create("Handler {$handlerClass} must implement WithdrawHandlerInterface")
                ->withDisplayMessage('处理器必须实现 WithdrawHandlerInterface 接口')
                ->withHttpStatus(500);
        }
        
        // 设置配置
        if ($channel && $channel->handler && method_exists($handler, 'withConfig')) {
            // Get the handler entity and its config
            $handlerEntity = $channel->handler;
            if ($handlerEntity instanceof \Dybasedev\LunaPrototype\Foundation\Handler\Models\Handler) {
                $configData = $handlerEntity->getAttributeValue('config');
                if (is_array($configData) && !empty($configData)) {
                    $handler->withConfig($configData);
                }
            }
        }
        
        // 设置处理器ID
        if ($channel && $channel->handler && property_exists($handler, 'handlerId')) {
            $handler->handlerId = $channel->handler->id;
        }
        
        // 设置模型实例
        if ($channel && method_exists($handler, 'loadInstance')) {
            $handler->loadInstance($channel);
        }
        
        return $handler;
    }

    /**
     * 处理入金交易
     */
    public function processDeposit(DepositTransaction $transaction): DepositResult
    {
        // Load channel with handler relationship
        $transaction->load('channel.handler');
        
        if (!$transaction->channel->handler) {
            throw LunaException::create("No handler configured for deposit channel: {$transaction->channel->name}")
                ->withDisplayMessage('渠道未配置处理器')
                ->withHttpStatus(500);
        }
        
        $handler = $this->getDepositHandler($transaction->channel->handler->handler, $transaction->channel);
        
        if (!$handler) {
            throw LunaException::create("No handler found for deposit channel: {$transaction->channel->name}")
                ->withDisplayMessage('找不到对应的入金处理器')
                ->withHttpStatus(500);
        }
        
        return $handler->process($transaction);
    }

    /**
     * 处理出金交易
     */
    public function processWithdraw(WithdrawTransaction $transaction): WithdrawResult
    {
        // Load channel with handler relationship
        $transaction->load('channel.handler');
        
        if (!$transaction->channel->handler) {
            throw LunaException::create("No handler configured for withdraw channel: {$transaction->channel->name}")
                ->withDisplayMessage('渠道未配置处理器')
                ->withHttpStatus(500);
        }
        
        $handler = $this->getWithdrawHandler($transaction->channel->handler->handler, $transaction->channel);
        
        if (!$handler) {
            throw LunaException::create("No handler found for withdraw channel: {$transaction->channel->name}")
                ->withDisplayMessage('找不到对应的出金处理器')
                ->withHttpStatus(500);
        }
        
        return $handler->process($transaction);
    }

    /**
     * 获取入金统计
     */
    public function getDepositStatistics(Model $owner, array $filters = []): array
    {
        $query = $this->queryDepositTransactions($owner)->completed();
        
        if (isset($filters['start_date'])) {
            $query->where('completed_at', '>=', $filters['start_date']);
        }
        
        if (isset($filters['end_date'])) {
            $query->where('completed_at', '<=', $filters['end_date']);
        }
        
        if (isset($filters['currency_id'])) {
            $query->where('currency_id', $filters['currency_id']);
        }
        
        if (isset($filters['channel_id'])) {
            $query->where('channel_id', $filters['channel_id']);
        }
        
        return [
            'total_amount' => $query->sum('amount'),
            'total_fee' => $query->sum('fee'),
            'total_count' => $query->count(),
            'net_amount' => $query->sum(DB::raw('amount - fee')),
        ];
    }

    /**
     * 获取出金统计
     */
    public function getWithdrawStatistics(Model $owner, array $filters = []): array
    {
        $query = $this->queryWithdrawTransactions($owner)->completed();
        
        if (isset($filters['start_date'])) {
            $query->where('completed_at', '>=', $filters['start_date']);
        }
        
        if (isset($filters['end_date'])) {
            $query->where('completed_at', '<=', $filters['end_date']);
        }
        
        if (isset($filters['currency_id'])) {
            $query->where('currency_id', $filters['currency_id']);
        }
        
        if (isset($filters['channel_id'])) {
            $query->where('channel_id', $filters['channel_id']);
        }
        
        return [
            'total_amount' => $query->sum('amount'),
            'total_fee' => $query->sum('fee'),
            'total_count' => $query->count(),
            'net_amount' => $query->sum(DB::raw('amount - fee')),
        ];
    }
}
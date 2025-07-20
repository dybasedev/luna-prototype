<?php

namespace Dybasedev\LunaPrototype\Trade;

use Dybasedev\LunaPrototype\Foundation\LunaModule;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Trade\Models\TradeTransaction;
use Dybasedev\LunaPrototype\Trade\Models\TradeTradable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * 交易管理对象
 * 
 * 这是 Luna 框架中交易系统的核心管理类，提供了完整的交易管理功能。
 * 该类负责管理交易的创建、查询、状态变更等操作。
 * 
 * 主要功能包括：
 * - 创建和管理交易
 * - 处理交易状态变更
 * - 查询交易信息
 * - 管理可交易对象
 * - 交易过期检查
 * 
 * @package Dybasedev\LunaPrototype\Trade
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class LunaTrade extends LunaModule
{
    /**
     * 交易管理对象构造函数
     *
     * @param LunaTradeConfigure $configure 交易配置对象
     * @param LunaHandler $handler 处理器管理对象
     * @param Cache $cache 缓存接口实例
     */
    public function __construct(
        protected(set) LunaTradeConfigure $configure,
        protected(set) LunaHandler $handler,
        protected Cache $cache
    ) {
        
    }
    
    /**
     * 创建交易
     * 
     * 使用指定的交易流程处理器创建一个新的交易
     * 
     * @param SessionHolder $owner 交易所有者
     * @param string|int $handlerName 处理器名称或ID
     * @param Tradable|Tradable[] $tradables 可交易对象
     * @param array $options 额外选项
     * @return Transaction 创建的交易实例
     * @throws LunaException
     */
    public function createTransaction(
        SessionHolder $owner,
        string|int $handlerName,
        Tradable|array $tradables,
        array $options = []
    ): Transaction {
        try {
            // 获取处理器
            $flowHandler = $this->getFlowHandler($handlerName);
            
            // 创建交易的回调函数
            $callback = function () use ($flowHandler, $owner, $tradables, $options) {
                // 创建交易
                $transaction = $flowHandler->createTransaction($owner, $tradables, $options);
                
                // 生成交易编号
                $transactionNumber = $this->generateTransactionNumber($transaction);
                $transaction->setTransactionNumber($transactionNumber);
                $transaction->save();
                
                return $transaction;
            };
            
            // 检查是否已在事务中，避免嵌套事务问题
            if (DB::transactionLevel() > 0) {
                return $callback();
            }
            
            return DB::transaction($callback);
        } catch (Throwable $e) {
            if ($e instanceof LunaException) {
                throw $e;
            }
            throw LunaException::create($e)
                ->withDisplayMessage('创建交易时发生错误')
                ->withHttpStatus(500);
        }
    }
    
    /**
     * 获取交易流程处理器
     * 
     * @param string|int $handlerName 处理器名称或ID
     * @return TradeFlowHandler 处理器实例
     * @throws LunaException
     */
    public function getFlowHandler(string|int $handlerName): TradeFlowHandler
    {
        try {
            $handler = $this->handler->createHandlerInstance($handlerName);
            
            if (!$handler instanceof TradeFlowHandler) {
                throw LunaException::create('Invalid trade flow handler')
                    ->withDisplayMessage('无效的交易流程处理器')
                    ->withData(['handler' => $handlerName])
                    ->withHttpStatus(400);
            }
            
            return $handler;
        } catch (Throwable $e) {
            if ($e instanceof LunaException) {
                throw $e;
            }
            throw LunaException::create($e)
                ->withDisplayMessage('获取交易流程处理器时发生错误')
                ->withData(['handler' => $handlerName])
                ->withHttpStatus(500);
        }
    }
    
    /**
     * 生成交易编号
     * 
     * @param Transaction $transaction 交易实例
     * @return string 交易编号
     * @throws LunaException
     */
    protected function generateTransactionNumber(Transaction $transaction): string
    {
        try {
            // 首先尝试从交易流程处理器获取编号生成器
            $handler = $this->getFlowHandler($transaction->handler_id);
            if ($handler instanceof TradeFlowHandler) {
                $generator = $handler->getTransactionNumberGenerator();
            } else {
                // 如果处理器不可用，使用全局默认生成器
                $generator = $this->configure->getTransactionNumberGenerator();
            }
            
            if (!$generator) {
                throw new \RuntimeException('Transaction number generator not configured');
            }
            
            return $generator->generate($transaction);
        } catch (\Exception $e) {
            throw LunaException::create($e)
                ->withDisplayMessage('生成交易编号失败')
                ->withHttpStatus(500);
        }
    }
    
    /**
     * 更新交易状态
     * 
     * @param Transaction $transaction 交易实例
     * @param int $newStatus 新状态
     * @param array $context 上下文信息
     * @return StatusChangeResult 状态变更结果
     * @throws LunaException
     */
    public function updateTransactionStatus(
        Transaction $transaction,
        int $newStatus,
        array $context = []
    ): StatusChangeResult {
        try {
            // 检查交易是否可以变更状态
            if (!$transaction->canChangeStatus()) {
                throw LunaException::create('Transaction cannot change status')
                    ->withDisplayMessage('交易状态无法变更')
                    ->withData([
                        'transaction_id' => $transaction->getTransactionId(),
                        'current_status' => $transaction->getStatus(),
                        'is_completed' => $transaction instanceof TradeTransaction ? $transaction->is_completed : false,
                        'is_finished' => $transaction->isFinished(),
                    ])
                    ->withHttpStatus(400);
            }
            
            // 获取处理器
            $flowHandler = $this->getFlowHandler($transaction->getHandlerId());
            
            // 检查状态变更是否合法
            if (!$flowHandler->isValidStatusTransition($transaction->getStatus(), $newStatus)) {
                throw LunaException::create('Invalid status transition')
                    ->withDisplayMessage('无效的状态变更')
                    ->withData([
                        'from_status' => $transaction->getStatus(),
                        'to_status' => $newStatus,
                    ])
                    ->withHttpStatus(400);
            }
            
            // 调用处理器处理状态变更
            $result = $flowHandler->handleStatusChange(
                $transaction,
                $transaction->getStatus(),
                $newStatus,
                $context
            );
            
            if ($result->isFailure()) {
                return $result;
            }
            
            // 更新状态
            $transaction->setStatus($newStatus);
            
            // 处理特殊状态
            if ($newStatus === $flowHandler->getCompletedStatus()) {
                $transaction->markAsCompleted();
            } elseif ($newStatus === $flowHandler->getCanceledStatus()) {
                $reason = $context['reason'] ?? null;
                $transaction->markAsCanceled($reason);
                $transaction->markAsFinished();
            }
            
            $saved = $transaction->save();
            
            return $saved 
                ? StatusChangeResult::success(['transaction_id' => $transaction->getTransactionId()])
                : StatusChangeResult::failure('Failed to save transaction');
        } catch (Throwable $e) {
            if ($e instanceof LunaException) {
                throw $e;
            }
            throw LunaException::create($e)
                ->withDisplayMessage('更新交易状态时发生错误')
                ->withHttpStatus(500);
        }
    }
    
    /**
     * 完成交易
     * 
     * @param Transaction $transaction 交易实例
     * @param array $context 上下文信息
     * @return void
     * @throws LunaException
     */
    public function completeTransaction(
        Transaction $transaction,
        array $context = []
    ): void {
        try {
            $flowHandler = $this->getFlowHandler($transaction->getHandlerId());
            
            // 完成交易的回调函数
            $callback = function () use ($flowHandler, $transaction, $context) {
                // 调用处理器完成交易
                $flowHandler->completeTransaction($transaction, $context);
                
                // 更新状态
                $this->updateTransactionStatus(
                    $transaction,
                    $flowHandler->getCompletedStatus(),
                    $context
                );
                
                // 标记为结束
                $transaction->markAsFinished();
                $transaction->save();
            };
            
            // 检查是否已在事务中，避免嵌套事务问题
            if (DB::transactionLevel() > 0) {
                $callback();
            } else {
                DB::transaction($callback);
            }
        } catch (Throwable $e) {
            if ($e instanceof LunaException) {
                throw $e;
            }
            throw LunaException::create($e)
                ->withDisplayMessage('完成交易时发生错误')
                ->withHttpStatus(500);
        }
    }
    
    /**
     * 取消交易
     * 
     * @param Transaction $transaction 交易实例
     * @param string $reason 取消原因
     * @param array $context 上下文信息
     * @return void
     * @throws LunaException
     */
    public function cancelTransaction(
        Transaction $transaction,
        string $reason,
        array $context = []
    ): void {
        try {
            $flowHandler = $this->getFlowHandler($transaction->getHandlerId());
            
            // 取消交易的回调函数
            $callback = function () use ($flowHandler, $transaction, $reason, $context) {
                // 添加取消原因到上下文
                $context['reason'] = $reason;
                
                // 调用处理器取消交易
                $flowHandler->cancelTransaction($transaction, $reason, $context);
                
                // 更新状态
                $this->updateTransactionStatus(
                    $transaction,
                    $flowHandler->getCanceledStatus(),
                    $context
                );
            };
            
            // 检查是否已在事务中，避免嵌套事务问题
            if (DB::transactionLevel() > 0) {
                $callback();
            } else {
                DB::transaction($callback);
            }
        } catch (Throwable $e) {
            if ($e instanceof LunaException) {
                throw $e;
            }
            throw LunaException::create($e)
                ->withDisplayMessage('取消交易时发生错误')
                ->withHttpStatus(500);
        }
    }
    
    /**
     * 获取用户的交易列表
     * 
     * @param SessionHolder $owner 交易所有者
     * @param array $filters 过滤条件
     * @param int $perPage 每页数量
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getOwnerTransactions(
        SessionHolder $owner,
        array $filters = [],
        int $perPage = 20
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        $query = $this->configure->transactionModel::query()
            ->where('owner_id', $owner->getOperatorId())
            ->where('owner_type', $owner->getOperatorType());
        
        // 应用过滤条件
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['handler_id'])) {
            $query->where('handler_id', $filters['handler_id']);
        }
        
        if (isset($filters['is_completed'])) {
            $query->where('is_completed', $filters['is_completed']);
        }
        
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        
        // 排序
        $query->orderBy('created_at', 'desc');
        
        return $query->paginate($perPage);
    }
    
    /**
     * 根据ID获取交易
     * 
     * @param int $id 交易ID
     * @param bool $withRelations 是否加载关联数据
     * @return Transaction
     * @throws LunaException
     */
    public function getTransaction(int $id, bool $withRelations = false): Transaction
    {
        try {
            $query = $this->configure->transactionModel::query();
            
            if ($withRelations) {
                $query->with(['tradables']);
            }
            
            $transaction = $query->find($id);
            
            if (!$transaction) {
                throw LunaException::create('Transaction not found')
                    ->withDisplayMessage('交易不存在')
                    ->withData(['id' => $id])
                    ->withHttpStatus(404);
            }
            
            return $transaction;
        } catch (Throwable $e) {
            if ($e instanceof LunaException) {
                throw $e;
            }
            throw LunaException::create($e)
                ->withDisplayMessage('获取交易信息时发生错误')
                ->withHttpStatus(500);
        }
    }
    
    /**
     * 根据交易编号获取交易
     * 
     * @param string $transactionNumber 交易编号
     * @return Transaction|null
     * @throws LunaException
     */
    public function getTransactionByNumber(string $transactionNumber): ?Transaction
    {
        try {
            $generator = $this->configure->transactionNumberGenerator;
            if (!$generator) {
                throw new \RuntimeException('Transaction number generator not configured');
            }
            
            // 验证编号格式
            if (!$generator->validate($transactionNumber)) {
                return null;
            }
            
            // 解析出交易ID
            $transactionId = $generator->parseId($transactionNumber);
            if ($transactionId === null) {
                return null;
            }
            
            // 获取交易
            $transaction = $this->getTransaction($transactionId);
            
            // 验证编号是否匹配
            if ($transaction->getTransactionNumber() !== $transactionNumber) {
                return null;
            }
            
            return $transaction;
        } catch (LunaException $e) {
            // 如果是404错误，返回null
            if ($e->getHttpStatusCode() === 404) {
                return null;
            }
            throw $e;
        } catch (Throwable $e) {
            throw LunaException::create($e)
                ->withDisplayMessage('根据编号获取交易时发生错误')
                ->withHttpStatus(500);
        }
    }
    
    /**
     * 解析交易编号
     * 
     * @param string $transactionNumber 交易编号
     * @return array|null 返回解析结果，包含 id 和 timestamp（如果支持）
     * @throws LunaException
     */
    public function parseTransactionNumber(string $transactionNumber): ?array
    {
        try {
            $generator = $this->configure->transactionNumberGenerator;
            if (!$generator) {
                throw new \RuntimeException('Transaction number generator not configured');
            }
            
            if (!$generator->validate($transactionNumber)) {
                return null;
            }
            
            $result = [
                'id' => $generator->parseId($transactionNumber),
                'format' => $generator->getFormatDescription(),
            ];
            
            // 如果生成器支持解析时间戳
            if (method_exists($generator, 'parseTimestamp')) {
                $timestamp = $generator->parseTimestamp($transactionNumber);
                if ($timestamp) {
                    $result['timestamp'] = $timestamp->format('Y-m-d H:i:s');
                }
            }
            
            return $result;
        } catch (\Exception $e) {
            throw LunaException::create($e)
                ->withDisplayMessage('解析交易编号时发生错误')
                ->withHttpStatus(500);
        }
    }
    
    /**
     * 检查并处理过期交易
     * 
     * @return int 处理的交易数量
     */
    public function checkAndHandleExpiredTransactions(): int
    {
        if (!$this->configure->enableExpiredCheck) {
            return 0;
        }
        
        $expiredTransactions = $this->configure->transactionModel::query()
            ->where('is_finished', false)
            ->where('is_completed', false)
            ->where('expired_at', '<=', now())
            ->get();
        
        $count = 0;
        
        foreach ($expiredTransactions as $transaction) {
            try {
                $flowHandler = $this->getFlowHandler($transaction->getHandlerId());
                $flowHandler->handleExpiredTransaction($transaction);
                $count++;
            } catch (Throwable $e) {
                // 记录错误但继续处理其他交易
                report($e);
            }
        }
        
        return $count;
    }
    
    /**
     * 创建可交易对象
     * 
     * @param array $data 可交易对象数据
     * @return TradeTradable
     * @throws LunaException
     */
    public function createTradable(array $data): TradeTradable
    {
        try {
            // 验证必填字段
            if (empty($data['code']) || empty($data['handler_id'])) {
                throw LunaException::create('Missing required fields')
                    ->withDisplayMessage('缺少必填字段')
                    ->withData(['required' => ['code', 'handler_id']])
                    ->withHttpStatus(400);
            }
            
            // 验证处理器是否存在
            if (!$this->handler->existsEntityHandler($data['handler_id'])) {
                throw LunaException::create('Handler not found')
                    ->withDisplayMessage('处理器不存在')
                    ->withData(['handler_id' => $data['handler_id']])
                    ->withHttpStatus(404);
            }
            
            /** @var TradeTradable $tradable */
            $tradable = new ($this->configure->tradableModel)();
            $tradable->fill($data);
            $tradable->save();
            
            return $tradable;
        } catch (Throwable $e) {
            if ($e instanceof LunaException) {
                throw $e;
            }
            throw LunaException::create($e)
                ->withDisplayMessage('创建可交易对象时发生错误')
                ->withHttpStatus(500);
        }
    }
}
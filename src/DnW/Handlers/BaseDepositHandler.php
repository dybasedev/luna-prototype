<?php

namespace Dybasedev\LunaPrototype\DnW\Handlers;

use Dybasedev\LunaPrototype\DnW\Handlers\Contracts\DepositHandlerInterface;
use Dybasedev\LunaPrototype\DnW\Models\DepositTransaction;
use Dybasedev\LunaPrototype\DnW\Models\DepositBinding;
use Dybasedev\LunaPrototype\DnW\Models\DepositChannel;
use Dybasedev\LunaPrototype\DnW\DepositResult;
use Dybasedev\LunaPrototype\DnW\TransactionStatus;
use Dybasedev\LunaPrototype\DnW\LunaDnWConfigure;
use Dybasedev\LunaPrototype\Foundation\Handler\BaseHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\ModelHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\WithModelInstance;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 入金处理器基类
 */
abstract class BaseDepositHandler extends BaseHandler implements DepositHandlerInterface, ModelHandler
{
    use WithModelInstance;

    /**
     * 模块配置实例
     */
    protected ?LunaDnWConfigure $moduleConfigure = null;

    /**
     * 设置模块配置
     */
    public function withModuleConfigure(LunaDnWConfigure $configure): static
    {
        $this->moduleConfigure = $configure;
        return $this;
    }

    /**
     * 获取模块配置
     */
    protected function getModuleConfigure(): LunaDnWConfigure
    {
        if ($this->moduleConfigure === null) {
            $this->moduleConfigure = app(LunaDnWConfigure::class);
        }
        return $this->moduleConfigure;
    }

    /**
     * 获取配置仓库
     */
    public static function configurationRepository(): string
    {
        return \Dybasedev\LunaPrototype\Foundation\Configuration\Repository::class;
    }

    /**
     * 创建入金交易
     */
    public function createTransaction(
        Model $owner,
        string $amount,
        array $options = []
    ): DepositTransaction {
        /** @var DepositChannel $channel */
        $channel = $this->modelInstance;
        
        if (!$channel || !$channel->is_active) {
            throw LunaException::create('Deposit channel is not active')
                ->withDisplayMessage('入金渠道未激活')
                ->withHttpStatus(400);
        }

        // 预处理金额（如单位转换等）
        $amount = $this->preprocessAmount($amount, $options);

        // 验证金额
        if (!$this->validateAmount($amount)) {
            throw LunaException::create('Invalid amount')
                ->withDisplayMessage('金额不符合要求')
                ->withHttpStatus(400);
        }

        return DB::transaction(function () use ($owner, $channel, $amount, $options) {
            $modelClass = $this->getModuleConfigure()->depositTransactionModel;
            
            $transaction = new $modelClass([
                'channel_id' => $channel->id,
                'owner_id' => $owner->getKey(),
                'owner_type' => hash_code($owner->getMorphClass()),
                'amount' => $amount,
                'fee' => $this->calculateFee($amount),
                'currency_id' => $options['currency_id'] ?? null,
                'external_id' => null,
                'origin_id' => $options['origin_id'] ?? null,
                'origin_type' => isset($options['origin_type']) ? hash_code($options['origin_type']) : null,
                'extra_data' => $options['extra_data'] ?? null,
                'status' => TransactionStatus::Pending->getCode(),
            ]);
            
            $transaction->save();
            
            return $transaction;
        });
    }

    /**
     * 处理入金交易
     */
    public function process(DepositTransaction $transaction): DepositResult
    {
        try {
            // 标记为处理中
            $transaction->markAsProcessing([
                'handler' => static::class,
                'started_at' => now()->toDateTimeString(),
            ]);

            // 执行具体的处理逻辑
            $result = $this->doProcess($transaction);

            if ($result->isSuccess()) {
                // 如果直接成功，标记为成功
                if ($result->isCompleted()) {
                    // 更新外部ID
                    $transaction->external_id = $result->getExternalId();
                    $transaction->save();
                    
                    $transaction->markAsSuccess([
                        'handler' => static::class,
                        'completed_at' => now()->toDateTimeString(),
                        'external_id' => $result->getExternalId(),
                    ]);
                }
            } else {
                // 处理失败
                $transaction->markAsFailed([
                    'handler' => static::class,
                    'failed_at' => now()->toDateTimeString(),
                    'error' => $result->getError(),
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Deposit handler process error', [
                'handler' => static::class,
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            $transaction->markAsFailed([
                'handler' => static::class,
                'error' => $e->getMessage(),
            ]);

            return DepositResult::failed($e->getMessage());
        }
    }

    /**
     * 查询交易状态
     */
    public function query(DepositTransaction $transaction): array
    {
        return [
            'status' => $transaction->getStatus()?->value,
            'external_id' => $transaction->external_id,
            'amount' => $transaction->amount,
            'fee' => $transaction->fee,
            'net_amount' => $transaction->getNetAmount(),
        ];
    }

    /**
     * 验证金额
     */
    public function validateAmount(string $amount): bool
    {
        if (!$this->config) {
            return true;
        }

        $enableFixedLimit = $this->config->get('enable_fixed_limit', false);
        $enableRangeLimit = $this->config->get('enable_range_limit', false);

        // 如果没有开启任何限制，则直接返回 true
        if (!$enableRangeLimit && !$enableFixedLimit) {
            return true;
        }

        $allow = false;
        
        if ($enableFixedLimit) {
            $fixedLimits = $this->config->get('fixed_limit', []);
            $allow = in_array($amount, array_map('strval', $fixedLimits));
        }

        if (!$allow && $enableRangeLimit) {
            $rangeLimit = $this->config->get('range_limit', []);
            
            if (count($rangeLimit) === 1) {
                $allow = $amount >= $rangeLimit[0];
            } elseif (count($rangeLimit) === 2) {
                $allow = $amount >= $rangeLimit[0] && $amount <= $rangeLimit[1];
            }
        }

        return $allow;
    }

    /**
     * 验证绑定账户
     */
    public function validateBinding(DepositBinding $binding): bool
    {
        // 检查绑定是否属于当前渠道
        if ($binding->channel_id !== $this->modelInstance->id) {
            return false;
        }

        // 检查绑定是否激活和验证
        return $binding->is_active && $binding->isVerified();
    }

    /**
     * 计算手续费
     */
    protected function calculateFee(string $amount): string
    {
        if (!$this->config) {
            return '0';
        }

        $feeRate = $this->config->get('fee_rate', 0);
        $fixedFee = $this->config->get('fixed_fee', 0);

        $fee = ($amount * $feeRate / 100) + $fixedFee;

        return number_format($fee, 2, '.', '');
    }

    /**
     * 处理回调的默认实现
     */
    public function handleCallback(Request $request): Response
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    /**
     * 更新交易状态并记录日志
     */
    protected function updateTransactionStatus(
        DepositTransaction $transaction,
        TransactionStatus $status,
        array $data = []
    ): void {
        $fromStatus = $transaction->status;
        
        $transaction->transitionTo($status, array_merge($data, [
            'handler' => static::class,
            'event_id' => $data['event_id'] ?? null,
            'operator_id' => $data['operator_id'] ?? null,
            'operator_type' => $data['operator_type'] ?? null,
        ]));
    }

    /**
     * 执行具体的处理逻辑
     */
    abstract protected function doProcess(DepositTransaction $transaction): DepositResult;
    
    /**
     * 预处理金额
     * 
     * 可以在子类中重写此方法来实现金额的预处理逻辑，
     * 如单位转换、汇率计算等
     * 
     * @param string $amount 原始金额
     * @param array $options 选项参数
     * @return string 处理后的金额
     */
    protected function preprocessAmount(string $amount, array $options = []): string
    {
        // 默认实现：直接返回原始金额
        // 子类可以重写此方法来实现自定义的金额预处理逻辑
        return $amount;
    }
}
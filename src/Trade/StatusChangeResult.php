<?php

namespace Dybasedev\LunaPrototype\Trade;

/**
 * 状态变更结果类
 * 
 * 封装交易状态变更的结果，不仅包含成功/失败标记，
 * 还可以携带业务相关的数据，如支付参数、错误信息等。
 * 
 * @package Dybasedev\LunaPrototype\Trade
 * @author Luna Prototype Team
 * @since 1.0.0
 */
class StatusChangeResult
{
    /**
     * @var bool 状态变更是否成功
     */
    protected bool $success;
    
    /**
     * @var string|null 失败原因
     */
    protected ?string $reason;
    
    /**
     * @var array 附加数据
     */
    protected array $data;
    
    /**
     * @var string|null 建议的下一步操作
     */
    protected ?string $nextAction;
    
    /**
     * @var bool 是否允许重试
     */
    protected bool $retryable;
    
    /**
     * 构造函数
     * 
     * @param bool $success 是否成功
     * @param string|null $reason 失败原因
     * @param array $data 附加数据
     * @param string|null $nextAction 建议的下一步操作
     * @param bool $retryable 是否允许重试
     */
    public function __construct(
        bool $success,
        ?string $reason = null,
        array $data = [],
        ?string $nextAction = null,
        bool $retryable = false
    ) {
        $this->success = $success;
        $this->reason = $reason;
        $this->data = $data;
        $this->nextAction = $nextAction;
        $this->retryable = $retryable;
    }
    
    /**
     * 创建成功结果
     * 
     * @param array $data 附加数据
     * @return static
     */
    public static function success(array $data = []): static
    {
        return new static(true, null, $data);
    }
    
    /**
     * 创建失败结果
     * 
     * @param string $reason 失败原因
     * @param array $data 附加数据
     * @param bool $retryable 是否允许重试
     * @return static
     */
    public static function failure(string $reason, array $data = [], bool $retryable = false): static
    {
        return new static(false, $reason, $data, null, $retryable);
    }
    
    /**
     * 创建需要额外操作的结果
     * 
     * @param string $nextAction 下一步操作
     * @param array $data 附加数据
     * @return static
     */
    public static function needsAction(string $nextAction, array $data = []): static
    {
        return new static(false, 'Action required', $data, $nextAction, true);
    }
    
    /**
     * 是否成功
     * 
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }
    
    /**
     * 是否失败
     * 
     * @return bool
     */
    public function isFailure(): bool
    {
        return !$this->success;
    }
    
    /**
     * 获取失败原因
     * 
     * @return string|null
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }
    
    /**
     * 获取附加数据
     * 
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }
    
    /**
     * 获取指定的数据项
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
    
    /**
     * 获取建议的下一步操作
     * 
     * @return string|null
     */
    public function getNextAction(): ?string
    {
        return $this->nextAction;
    }
    
    /**
     * 是否需要额外操作
     * 
     * @return bool
     */
    public function hasNextAction(): bool
    {
        return $this->nextAction !== null;
    }
    
    /**
     * 是否允许重试
     * 
     * @return bool
     */
    public function isRetryable(): bool
    {
        return $this->retryable;
    }
    
    /**
     * 添加数据
     * 
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function withData(string $key, mixed $value): static
    {
        $this->data[$key] = $value;
        return $this;
    }
    
    /**
     * 设置下一步操作
     * 
     * @param string $action
     * @return $this
     */
    public function withNextAction(string $action): static
    {
        $this->nextAction = $action;
        return $this;
    }
    
    /**
     * 转换为数组
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'reason' => $this->reason,
            'data' => $this->data,
            'next_action' => $this->nextAction,
            'retryable' => $this->retryable,
        ];
    }
}
<?php

namespace Dybasedev\LunaPrototype\DnW;

/**
 * 出金结果
 */
class WithdrawResult
{
    /**
     * 构造函数
     */
    public function __construct(
        protected bool $success,
        protected bool $completed = false,
        protected ?string $externalId = null,
        protected ?string $error = null,
        protected array $extra = []
    ) {
    }

    /**
     * 创建成功结果
     */
    public static function success(
        bool $completed = false,
        ?string $externalId = null,
        array $extra = []
    ): self {
        return new self(
            success: true,
            completed: $completed,
            externalId: $externalId,
            extra: $extra
        );
    }

    /**
     * 创建失败结果
     */
    public static function failed(string $error, array $extra = []): self
    {
        return new self(
            success: false,
            error: $error,
            extra: $extra
        );
    }

    /**
     * 是否成功
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * 是否已完成
     */
    public function isCompleted(): bool
    {
        return $this->completed;
    }

    /**
     * 获取外部交易ID
     */
    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    /**
     * 获取错误信息
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * 获取额外数据
     */
    public function getExtra(): array
    {
        return $this->extra;
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'completed' => $this->completed,
            'external_id' => $this->externalId,
            'error' => $this->error,
            'extra' => $this->extra,
        ];
    }
}
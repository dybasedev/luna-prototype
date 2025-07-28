<?php

namespace Dybasedev\LunaPrototype\DnW\Handlers\Contracts;

use Dybasedev\LunaPrototype\DnW\Models\DepositTransaction;
use Dybasedev\LunaPrototype\DnW\Models\DepositBinding;
use Dybasedev\LunaPrototype\DnW\DepositResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * 入金处理器接口
 */
interface DepositHandlerInterface
{
    /**
     * 获取处理器名称
     */
    public function getName(): string;

    /**
     * 获取处理器描述
     */
    public function getDescription(): string;

    /**
     * 创建入金交易
     */
    public function createTransaction(
        Model $owner,
        string $amount,
        array $options = []
    ): DepositTransaction;

    /**
     * 处理入金请求
     */
    public function process(DepositTransaction $transaction): DepositResult;

    /**
     * 处理回调
     */
    public function handleCallback(Request $request): Response;

    /**
     * 查询交易状态
     */
    public function query(DepositTransaction $transaction): array;

    /**
     * 验证金额是否合法
     */
    public function validateAmount(string $amount): bool;

    /**
     * 验证绑定账户
     */
    public function validateBinding(DepositBinding $binding): bool;

    /**
     * 获取支持的绑定类型
     */
    public function getSupportedBindingTypes(): array;
}
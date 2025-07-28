<?php

namespace Dybasedev\LunaPrototype\DnW\Handlers\Contracts;

use Dybasedev\LunaPrototype\DnW\Models\WithdrawTransaction;
use Dybasedev\LunaPrototype\DnW\Models\WithdrawBinding;
use Dybasedev\LunaPrototype\DnW\WithdrawResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * 出金处理器接口
 */
interface WithdrawHandlerInterface
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
     * 创建出金交易
     */
    public function createTransaction(
        Model $owner,
        string $amount,
        WithdrawBinding $binding,
        array $options = []
    ): WithdrawTransaction;

    /**
     * 处理出金请求
     */
    public function process(WithdrawTransaction $transaction): WithdrawResult;

    /**
     * 处理回调
     */
    public function handleCallback(Request $request): Response;

    /**
     * 查询交易状态
     */
    public function query(WithdrawTransaction $transaction): array;

    /**
     * 验证金额是否合法
     */
    public function validateAmount(string $amount): bool;

    /**
     * 验证账户信息
     */
    public function validateAccount(array $accountInfo): bool;

    /**
     * 获取支持的绑定类型
     */
    public function getSupportedBindingTypes(): array;
}
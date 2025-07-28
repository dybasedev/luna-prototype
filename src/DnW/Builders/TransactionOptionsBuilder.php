<?php

namespace Dybasedev\LunaPrototype\DnW\Builders;

use Dybasedev\LunaPrototype\DnW\TransactionSpecialMark;
use Illuminate\Database\Eloquent\Model;

/**
 * 交易选项构造器
 * 
 * 提供链式调用来构建交易创建选项
 */
class TransactionOptionsBuilder
{
    /**
     * 选项数组
     */
    protected array $options = [];

    /**
     * 创建构造器实例
     */
    public static function create(): static
    {
        return new static();
    }

    /**
     * 设置货币ID
     */
    public function currency(int $currencyId): static
    {
        $this->options['currency_id'] = $currencyId;
        return $this;
    }

    /**
     * 设置货币ID（别名）
     */
    public function currencyId(int $currencyId): static
    {
        return $this->currency($currencyId);
    }

    /**
     * 设置手续费
     */
    public function fee(string|float $fee): static
    {
        $this->options['fee'] = (string) $fee;
        return $this;
    }

    /**
     * 设置外部交易ID
     */
    public function externalId(string $externalId): static
    {
        $this->options['external_id'] = $externalId;
        return $this;
    }

    /**
     * 设置来源信息
     */
    public function origin(Model $origin): static
    {
        $this->options['origin_id'] = $origin->getKey();
        $this->options['origin_type'] = $origin->getMorphClass();
        return $this;
    }

    /**
     * 设置来源信息（通过ID和类型）
     */
    public function originBy(int|string $originId, string $originType): static
    {
        $this->options['origin_id'] = $originId;
        $this->options['origin_type'] = $originType;
        return $this;
    }

    /**
     * 设置来源ID
     */
    public function originId(int|string $originId): static
    {
        $this->options['origin_id'] = $originId;
        return $this;
    }

    /**
     * 设置来源类型
     */
    public function originType(string $originType): static
    {
        $this->options['origin_type'] = $originType;
        return $this;
    }

    /**
     * 设置特殊标记
     */
    public function specialMark(TransactionSpecialMark $mark): static
    {
        $this->options['special_mark'] = $mark->getCode();
        return $this;
    }

    /**
     * 标记为测试交易
     */
    public function asTest(): static
    {
        return $this->specialMark(TransactionSpecialMark::Test);
    }

    /**
     * 标记为开发环境交易
     */
    public function asDevelopment(): static
    {
        return $this->specialMark(TransactionSpecialMark::Development);
    }

    /**
     * 标记为演示交易
     */
    public function asDemo(): static
    {
        return $this->specialMark(TransactionSpecialMark::Demo);
    }

    /**
     * 标记为正常交易
     */
    public function asNormal(): static
    {
        return $this->specialMark(TransactionSpecialMark::Normal);
    }

    /**
     * 设置额外数据
     */
    public function extraData(array $data): static
    {
        $this->options['extra_data'] = $data;
        return $this;
    }

    /**
     * 添加额外数据字段
     */
    public function addExtraData(string $key, mixed $value): static
    {
        if (!isset($this->options['extra_data'])) {
            $this->options['extra_data'] = [];
        }
        $this->options['extra_data'][$key] = $value;
        return $this;
    }

    /**
     * 设置备注
     */
    public function remark(string $remark): static
    {
        return $this->addExtraData('remark', $remark);
    }

    /**
     * 设置用户IP
     */
    public function userIp(string $ip): static
    {
        return $this->addExtraData('user_ip', $ip);
    }

    /**
     * 设置客户端IP（别名）
     */
    public function clientIp(string $ip): static
    {
        return $this->addExtraData('client_ip', $ip);
    }

    /**
     * 设置用户代理
     */
    public function userAgent(string $userAgent): static
    {
        return $this->addExtraData('user_agent', $userAgent);
    }

    /**
     * 设置请求来源
     */
    public function source(string $source): static
    {
        return $this->addExtraData('source', $source);
    }

    /**
     * 构建选项数组
     */
    public function build(): array
    {
        return $this->options;
    }

    /**
     * 获取选项数组（别名）
     */
    public function toArray(): array
    {
        return $this->build();
    }

    /**
     * 魔术方法：转换为数组时自动调用
     */
    public function __toArray(): array
    {
        return $this->build();
    }
}
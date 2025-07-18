<?php

namespace Dybasedev\LunaPrototype\Membership\Milestone\Conditions;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Membership\Milestone\DataProviders\DataProvider;
use Dybasedev\LunaPrototype\Membership\Milestone\MilestoneCondition;

/**
 * 基于数据提供者的条件
 * 
 * 使用数据提供者从各种数据源获取数据，然后进行条件判断
 */
class DataProviderCondition implements MilestoneCondition
{
    /**
     * @param DataProvider $dataProvider 数据提供者
     * @param string $operator 比较操作符
     * @param mixed $value 比较值
     * @param string|null $identifier 条件标识符
     * @param string|null $description 条件描述
     * @param array $dataParams 传递给数据提供者的额外参数
     */
    public function __construct(
        protected DataProvider $dataProvider,
        protected string $operator,
        protected mixed $value,
        protected ?string $identifier = null,
        protected ?string $description = null,
        protected array $dataParams = []
    ) {
        if (!in_array($operator, ['>', '>=', '<', '<=', '=', '!=', 'in', 'not_in', 'between'])) {
            throw new \InvalidArgumentException("Invalid operator: {$operator}");
        }
    }

    /**
     * 判断是否满足条件
     *
     * @param SessionHolder $owner 里程碑所有者
     * @param array $context 判断上下文
     * @return bool
     */
    public function isSatisfied(SessionHolder $owner, array $context = []): bool
    {
        // 合并上下文参数和预设参数
        $params = array_merge($this->dataParams, $context);
        
        // 从数据提供者获取数据
        $actualValue = $this->dataProvider->getData($owner, $params);
        
        // 执行比较
        return $this->compare($actualValue, $this->operator, $this->value);
    }

    /**
     * 执行比较操作
     *
     * @param mixed $actualValue 实际值
     * @param string $operator 操作符
     * @param mixed $expectedValue 期望值
     * @return bool
     */
    protected function compare(mixed $actualValue, string $operator, mixed $expectedValue): bool
    {
        return match ($operator) {
            '>' => $actualValue > $expectedValue,
            '>=' => $actualValue >= $expectedValue,
            '<' => $actualValue < $expectedValue,
            '<=' => $actualValue <= $expectedValue,
            '=' => $actualValue == $expectedValue,
            '!=' => $actualValue != $expectedValue,
            'in' => is_array($expectedValue) && in_array($actualValue, $expectedValue),
            'not_in' => is_array($expectedValue) && !in_array($actualValue, $expectedValue),
            'between' => is_array($expectedValue) && count($expectedValue) === 2 
                        && $actualValue >= $expectedValue[0] && $actualValue <= $expectedValue[1],
            default => false
        };
    }

    /**
     * 获取条件的唯一标识
     *
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier ?? "provider_{$this->dataProvider->getName()}_{$this->operator}";
    }

    /**
     * 获取条件描述
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description ?? "{$this->dataProvider->getName()} {$this->operator} {$this->formatValue($this->value)}";
    }

    /**
     * 格式化值用于显示
     *
     * @param mixed $value
     * @return string
     */
    protected function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            return '[' . implode(', ', $value) . ']';
        }
        return (string) $value;
    }

    /**
     * 获取条件配置
     *
     * @return array
     */
    public function getConfig(): array
    {
        return [
            'provider' => $this->dataProvider->getName(),
            'operator' => $this->operator,
            'value' => $this->value,
            'params' => $this->dataParams,
        ];
    }
}
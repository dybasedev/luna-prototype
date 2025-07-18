<?php

namespace Dybasedev\LunaPrototype\Membership\Milestone\Conditions;

use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Membership\Milestone\MilestoneCondition;

/**
 * 数值条件
 * 
 * 用于判断某个数值是否满足条件
 */
class NumericCondition implements MilestoneCondition
{
    /**
     * @param string $field 要判断的字段名
     * @param string $operator 操作符（>, >=, <, <=, =, !=）
     * @param float $value 比较值
     * @param string|null $identifier 条件标识符
     * @param string|null $description 条件描述
     */
    public function __construct(
        protected string $field,
        protected string $operator,
        protected float $value,
        protected ?string $identifier = null,
        protected ?string $description = null
    ) {
        if (!in_array($operator, ['>', '>=', '<', '<=', '=', '!='])) {
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
        // 优先从上下文获取值
        $actualValue = $context[$this->field] ?? null;
        
        // 如果上下文中没有，尝试从 SessionHolder 的上下文获取
        if ($actualValue === null) {
            $holderContext = $owner->getSessionHolderContext();
            if ($holderContext && isset($holderContext[$this->field])) {
                $actualValue = $holderContext[$this->field];
            }
        }
        
        if ($actualValue === null) {
            return false;
        }
        
        $actualValue = (float) $actualValue;
        
        return match ($this->operator) {
            '>' => $actualValue > $this->value,
            '>=' => $actualValue >= $this->value,
            '<' => $actualValue < $this->value,
            '<=' => $actualValue <= $this->value,
            '=' => $actualValue == $this->value,
            '!=' => $actualValue != $this->value,
        };
    }

    /**
     * 获取条件的唯一标识
     *
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier ?? "numeric_{$this->field}_{$this->operator}_{$this->value}";
    }

    /**
     * 获取条件描述
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description ?? "{$this->field} {$this->operator} {$this->value}";
    }

    /**
     * 获取条件配置
     *
     * @return array
     */
    public function getConfig(): array
    {
        return [
            'field' => $this->field,
            'operator' => $this->operator,
            'value' => $this->value,
        ];
    }
}
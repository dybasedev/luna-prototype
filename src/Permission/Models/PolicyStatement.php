<?php

namespace Dybasedev\LunaPrototype\Permission\Models;

use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Illuminate\Support\Arr;

/**
 * 策略声明解析器
 * 
 * 权限策略由以下基本元素组成：
 * - Effect: 效果（Allow/Deny）
 * - Action/NotAction: 操作
 * - Resource: 资源
 * - Condition: 条件
 * - Principal: 授权主体
 */
class PolicyStatement
{
    /**
     * 允许效果
     * 
     * @var string
     */
    public const string EFFECT_ALLOW = 'allow';

    /**
     * 拒绝效果
     * 
     * @var string
     */
    public const string EFFECT_DENY = 'deny';

    /**
     * 策略声明数据
     *
     * @var array
     */
    protected array $statement;

    /**
     * 创建策略声明实例
     *
     * @param array $statement
     */
    public function __construct(array $statement)
    {
        $this->statement = $statement;
    }

    /**
     * 获取效果
     *
     * @return string
     */
    public function getEffect(): string
    {
        return Arr::get($this->statement, 'effect', self::EFFECT_DENY);
    }

    /**
     * 是否允许
     *
     * @return bool
     */
    public function isAllow(): bool
    {
        return $this->getEffect() === self::EFFECT_ALLOW;
    }

    /**
     * 是否拒绝
     *
     * @return bool
     */
    public function isDeny(): bool
    {
        return $this->getEffect() === self::EFFECT_DENY;
    }

    /**
     * 获取操作列表
     *
     * @return array
     */
    public function getActions(): array
    {
        return Arr::wrap(Arr::get($this->statement, 'action', []));
    }

    /**
     * 获取排除的操作列表
     *
     * @return array
     */
    public function getNotActions(): array
    {
        return Arr::wrap(Arr::get($this->statement, 'not_action', []));
    }

    /**
     * 获取资源列表
     *
     * @return array
     */
    public function getResources(): array
    {
        return Arr::wrap(Arr::get($this->statement, 'resource', []));
    }

    /**
     * 获取条件
     *
     * @return array
     */
    public function getConditions(): array
    {
        return Arr::get($this->statement, 'condition', []);
    }

    /**
     * 获取授权主体
     *
     * @return array
     */
    public function getPrincipals(): array
    {
        return Arr::wrap(Arr::get($this->statement, 'principal', []));
    }

    /**
     * 检查操作是否匹配
     *
     * @param string $action
     * @return bool
     */
    public function matchAction(string $action): bool
    {
        $actions = $this->getActions();
        $notActions = $this->getNotActions();

        // 如果有 not_action，则排除这些操作
        if (!empty($notActions)) {
            return !$this->matchPattern($action, $notActions);
        }

        // 如果没有指定 action，则匹配所有
        if (empty($actions)) {
            return true;
        }

        return $this->matchPattern($action, $actions);
    }

    /**
     * 检查资源是否匹配
     *
     * @param string $resource
     * @return bool
     */
    public function matchResource(string $resource): bool
    {
        $resources = $this->getResources();

        // 如果没有指定资源，则匹配所有
        if (empty($resources)) {
            return true;
        }

        return $this->matchPattern($resource, $resources);
    }

    /**
     * 检查主体是否匹配
     *
     * @param string $principal
     * @return bool
     */
    public function matchPrincipal(string $principal): bool
    {
        $principals = $this->getPrincipals();

        // 如果没有指定主体，则匹配所有
        if (empty($principals)) {
            return true;
        }

        return in_array($principal, $principals, true) || in_array('*', $principals, true);
    }

    /**
     * 模式匹配
     *
     * @param string $value
     * @param array $patterns
     * @return bool
     */
    protected function matchPattern(string $value, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === '*' || $pattern === $value) {
                return true;
            }

            // 支持通配符匹配
            if (str_contains($pattern, '*') || str_contains($pattern, '?')) {
                // 先转义特殊字符，但保留 * 和 ?
                $escaped = preg_quote($pattern, '/');
                // 然后替换通配符
                $regex = '/^' . str_replace(['\\*', '\\?'], ['.*', '.'], $escaped) . '$/';
                if (preg_match($regex, $value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 验证策略声明
     *
     * @return bool
     * @throws LunaException
     */
    public function validate(): bool
    {
        // 验证效果
        $effect = $this->getEffect();
        if (!in_array($effect, [self::EFFECT_ALLOW, self::EFFECT_DENY], true)) {
            throw LunaException::create('策略效果必须是 allow 或 deny')
                ->withDisplayMessage('策略声明中的 effect 字段值无效');
        }

        // 验证必须有 action 或 not_action
        $actions = $this->getActions();
        $notActions = $this->getNotActions();
        if (empty($actions) && empty($notActions)) {
            throw LunaException::create('策略必须指定 action 或 not_action')
                ->withDisplayMessage('策略声明缺少操作定义');
        }

        // 验证资源
        $resources = $this->getResources();
        if (empty($resources)) {
            throw LunaException::create('策略必须指定 resource')
                ->withDisplayMessage('策略声明缺少资源定义');
        }

        // 验证条件格式（如果有）
        $conditions = $this->getConditions();
        if (!empty($conditions) && !is_array($conditions)) {
            throw LunaException::create('条件必须是数组格式')
                ->withDisplayMessage('策略声明中的 condition 字段格式无效');
        }

        return true;
    }

    /**
     * 获取原始声明数据
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->statement;
    }

    /**
     * 创建允许声明
     *
     * @param array|string $actions
     * @param array|string $resources
     * @param array $options
     * @return array
     */
    public static function allow($actions, $resources, array $options = []): array
    {
        return array_merge([
            'effect' => self::EFFECT_ALLOW,
            'action' => $actions,
            'resource' => $resources,
        ], $options);
    }

    /**
     * 创建拒绝声明
     *
     * @param array|string $actions
     * @param array|string $resources
     * @param array $options
     * @return array
     */
    public static function deny($actions, $resources, array $options = []): array
    {
        return array_merge([
            'effect' => self::EFFECT_DENY,
            'action' => $actions,
            'resource' => $resources,
        ], $options);
    }

    /**
     * 创建构建器
     *
     * @return PolicyStatementBuilder
     */
    public static function builder(): PolicyStatementBuilder
    {
        return new PolicyStatementBuilder();
    }
}

/**
 * 策略声明构建器
 */
class PolicyStatementBuilder
{
    /**
     * 效果
     * 
     * @var string
     */
    protected string $effect = PolicyStatement::EFFECT_ALLOW;

    /**
     * 操作列表
     * 
     * @var array
     */
    protected array $actions = [];

    /**
     * 排除的操作列表
     * 
     * @var array
     */
    protected array $notActions = [];

    /**
     * 资源列表
     * 
     * @var array
     */
    protected array $resources = [];

    /**
     * 条件
     * 
     * @var array
     */
    protected array $conditions = [];

    /**
     * 主体列表
     * 
     * @var array
     */
    protected array $principals = [];

    /**
     * 设置允许效果
     *
     * @return $this
     */
    public function allow(): static
    {
        $this->effect = PolicyStatement::EFFECT_ALLOW;
        return $this;
    }

    /**
     * 设置拒绝效果
     *
     * @return $this
     */
    public function deny(): static
    {
        $this->effect = PolicyStatement::EFFECT_DENY;
        return $this;
    }

    /**
     * 添加操作
     *
     * @param string|array $actions
     * @return $this
     */
    public function action($actions): static
    {
        $this->actions = array_merge($this->actions, Arr::wrap($actions));
        return $this;
    }

    /**
     * 添加排除的操作
     *
     * @param string|array $actions
     * @return $this
     */
    public function notAction($actions): static
    {
        $this->notActions = array_merge($this->notActions, Arr::wrap($actions));
        return $this;
    }

    /**
     * 添加资源
     *
     * @param string|array $resources
     * @return $this
     */
    public function resource($resources): static
    {
        $this->resources = array_merge($this->resources, Arr::wrap($resources));
        return $this;
    }

    /**
     * 添加条件
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function condition(string $key, $value): static
    {
        $this->conditions[$key] = $value;
        return $this;
    }

    /**
     * 添加主体
     *
     * @param string|array $principals
     * @return $this
     */
    public function principal($principals): static
    {
        $this->principals = array_merge($this->principals, Arr::wrap($principals));
        return $this;
    }

    /**
     * 构建策略声明
     *
     * @return array
     */
    public function build(): array
    {
        $statement = [
            'effect' => $this->effect,
        ];

        if (!empty($this->actions)) {
            $statement['action'] = count($this->actions) === 1 ? $this->actions[0] : $this->actions;
        }

        if (!empty($this->notActions)) {
            $statement['not_action'] = count($this->notActions) === 1 ? $this->notActions[0] : $this->notActions;
        }

        if (!empty($this->resources)) {
            $statement['resource'] = count($this->resources) === 1 ? $this->resources[0] : $this->resources;
        }

        if (!empty($this->conditions)) {
            $statement['condition'] = $this->conditions;
        }

        if (!empty($this->principals)) {
            $statement['principal'] = count($this->principals) === 1 ? $this->principals[0] : $this->principals;
        }

        return $statement;
    }
}
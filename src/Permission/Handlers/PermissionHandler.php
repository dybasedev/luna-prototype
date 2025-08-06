<?php

namespace Dybasedev\LunaPrototype\Permission\Handlers;

use Dybasedev\LunaPrototype\Permission\PermissionSubject;
use Dybasedev\LunaPrototype\Permission\Models\Policy;
use Dybasedev\LunaPrototype\Permission\Models\PolicyAssignment;
use Dybasedev\LunaPrototype\Permission\Models\PolicyStatement;
use Dybasedev\LunaPrototype\Permission\Resources\ResourceRegistry;
use Illuminate\Support\Collection;

/**
 * 标准权限检查处理器
 * 
 * 提供基于策略的权限检查实现，支持策略分配、条件评估等功能
 */
class PermissionHandler extends BasePermissionHandler
{
    /**
     * 获取处理器名称
     *
     * @return string
     */
    public function handlerName(): string
    {
        return '标准权限处理器';
    }

    /**
     * 获取处理器描述
     *
     * @return string
     */
    public function handlerDescription(): string
    {
        return '基于策略的标准权限检查处理器，支持策略分配、条件评估、优先级处理等功能';
    }


    /**
     * 检查权限
     *
     * @param PermissionSubject $subject 权限主体
     * @param string $action 操作
     * @param string $resource 资源
     * @param array $context 上下文条件
     * @return bool
     */
    public function check(
        PermissionSubject $subject,
        string $action,
        string $resource,
        array $context = []
    ): bool {
        // 检查是否为超级管理员
        if ($this->isSuperAdmin($subject)) {
            return true;
        }

        // 获取主体的所有策略
        $policies = $this->getSubjectPolicies($subject);

        // 如果没有任何策略，默认拒绝
        if ($policies->isEmpty()) {
            return false;
        }

        // 收集所有匹配的策略声明
        $matchedStatements = $this->collectMatchedStatements($policies, $action, $resource, $subject, $context);

        // 评估策略（拒绝优先）
        return $this->evaluateStatements($matchedStatements);
    }


    /**
     * 获取主体的所有策略
     *
     * @param PermissionSubject $subject
     * @return Collection
     */
    protected function getSubjectPolicies(PermissionSubject $subject): Collection
    {
        $cacheKey = $subject->getSubjectIdentifier();

        if (!isset($this->policyCache[$cacheKey])) {
            // 获取直接分配的策略
            $assignments = PolicyAssignment::bySubject(
                $subject->getSubjectType(),
                $subject->getSubjectId()
            )->active()->with('policy.current')->get();

            // 如果主体有 getAllPolicyAssignments 方法（如用户），使用它来获取包括继承的策略
            if (method_exists($subject, 'getAllPolicyAssignments')) {
                $assignments = $subject->getAllPolicyAssignments();
            }

            $this->policyCache[$cacheKey] = $assignments;
        }

        return $this->policyCache[$cacheKey];
    }

    /**
     * 收集匹配的策略声明
     *
     * @param Collection $assignments
     * @param string $action
     * @param string $resource
     * @param PermissionSubject $subject
     * @param array $context
     * @return Collection
     */
    protected function collectMatchedStatements(
        Collection $assignments,
        string $action,
        string $resource,
        PermissionSubject $subject,
        array $context
    ): Collection {
        $statements = new Collection();

        foreach ($assignments as $assignment) {
            $policy = $assignment->policy;
            $currentVersion = $policy->current;

            if (!$currentVersion) {
                continue;
            }

            $statement = new PolicyStatement($currentVersion->statement);

            // 检查主体是否匹配
            if (!$statement->matchPrincipal($subject->getSubjectIdentifier())) {
                continue;
            }

            // 检查操作是否匹配
            if (!$statement->matchAction($action)) {
                continue;
            }

            // 检查资源是否匹配
            if (!$statement->matchResource($resource)) {
                continue;
            }

            // 检查条件是否满足
            if (!$this->evaluateConditions($statement->getConditions(), $context, $assignment)) {
                continue;
            }

            $statements->push([
                'statement' => $statement,
                'assignment' => $assignment,
                'priority' => $this->calculatePriority($statement, $assignment),
            ]);
        }

        // 按优先级排序（高优先级在前）
        return $statements->sortByDesc('priority');
    }

    /**
     * 评估条件
     *
     * @param array $conditions
     * @param array $context
     * @param PolicyAssignment $assignment
     * @return bool
     */
    protected function evaluateConditions(array $conditions, array $context, PolicyAssignment $assignment): bool
    {
        if (empty($conditions)) {
            return true;
        }

        // 合并分配时的条件
        if ($assignment->conditions) {
            $conditions = array_merge($conditions, $assignment->conditions);
        }

        foreach ($conditions as $key => $condition) {
            if (!$this->evaluateCondition($key, $condition, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 评估单个条件
     *
     * @param string $key
     * @param mixed $condition
     * @param array $context
     * @return bool
     */
    protected function evaluateCondition(string $key, mixed $condition, array $context): bool
    {
        // 特殊条件处理
        switch ($key) {
            case 'ip_address':
                return $this->evaluateIpCondition($condition, $context['ip'] ?? request()->ip());
            
            case 'time_range':
                return $this->evaluateTimeRangeCondition($condition);
            
            case 'date_range':
                return $this->evaluateDateRangeCondition($condition);
            
            case 'resource_owner':
                return $this->evaluateResourceOwnerCondition($condition, $context);
            
            case 'resource_id':
                return $this->evaluateResourceIdCondition($condition, $context);
            
            case 'resource_attribute':
                return $this->evaluateResourceAttributeCondition($condition, $context);
            
            default:
                // 自定义条件评估
                return $this->evaluateCustomCondition($key, $condition, $context);
        }
    }

    /**
     * 评估IP条件
     *
     * @param mixed $condition
     * @param string $currentIp
     * @return bool
     */
    protected function evaluateIpCondition(mixed $condition, string $currentIp): bool
    {
        if (is_string($condition)) {
            return $currentIp === $condition;
        }

        if (is_array($condition)) {
            return in_array($currentIp, $condition, true);
        }

        return false;
    }

    /**
     * 评估时间范围条件
     *
     * @param array $condition
     * @return bool
     */
    protected function evaluateTimeRangeCondition(array $condition): bool
    {
        $currentTime = now()->format('H:i');
        $start = $condition['start'] ?? '00:00';
        $end = $condition['end'] ?? '23:59';

        return $currentTime >= $start && $currentTime <= $end;
    }

    /**
     * 评估日期范围条件
     *
     * @param array $condition
     * @return bool
     */
    protected function evaluateDateRangeCondition(array $condition): bool
    {
        $now = now();
        
        if (isset($condition['start'])) {
            $start = \Carbon\Carbon::parse($condition['start']);
            if ($now->lt($start)) {
                return false;
            }
        }

        if (isset($condition['end'])) {
            $end = \Carbon\Carbon::parse($condition['end']);
            if ($now->gt($end)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 评估资源所有者条件
     *
     * @param mixed $condition
     * @param array $context
     * @return bool
     */
    protected function evaluateResourceOwnerCondition(mixed $condition, array $context): bool
    {
        // 资源所有者由业务端在 context 中提供
        $resourceOwner = $context['resource_owner'] ?? null;
        
        if ($resourceOwner === null) {
            return false;
        }

        // 特殊值处理
        if ($condition === '@self') {
            // 检查当前用户是否是资源所有者
            $currentUser = $context['current_user'] ?? null;
            return $currentUser && $resourceOwner == $currentUser;
        }

        // 支持多个所有者
        if (is_array($condition)) {
            return in_array($resourceOwner, $condition);
        }

        return $resourceOwner == $condition;
    }

    /**
     * 评估资源ID条件
     *
     * @param mixed $condition
     * @param array $context
     * @return bool
     */
    protected function evaluateResourceIdCondition(mixed $condition, array $context): bool
    {
        $resourceId = $context['resource_id'] ?? null;
        
        if ($resourceId === null) {
            return false;
        }

        // 支持单个ID或ID列表
        if (is_array($condition)) {
            return in_array($resourceId, $condition);
        }

        // 支持范围条件
        if (is_array($condition) && isset($condition['operator'])) {
            return $this->evaluateOperatorCondition($resourceId, $condition);
        }

        return $resourceId == $condition;
    }

    /**
     * 评估资源属性条件
     *
     * @param mixed $condition
     * @param array $context
     * @return bool
     */
    protected function evaluateResourceAttributeCondition(mixed $condition, array $context): bool
    {
        if (!is_array($condition) || !isset($condition['attribute'], $condition['value'])) {
            return false;
        }

        $attributeName = $condition['attribute'];
        $expectedValue = $condition['value'];
        $operator = $condition['operator'] ?? '=';

        // 从 context 中获取资源属性
        $resourceAttributes = $context['resource_attributes'] ?? [];
        
        if (!isset($resourceAttributes[$attributeName])) {
            return false;
        }

        $actualValue = $resourceAttributes[$attributeName];

        return $this->evaluateOperatorCondition($actualValue, [
            'operator' => $operator,
            'value' => $expectedValue
        ]);
    }

    /**
     * 评估操作符条件
     *
     * @param mixed $value
     * @param array $condition
     * @return bool
     */
    protected function evaluateOperatorCondition(mixed $value, array $condition): bool
    {
        $operator = $condition['operator'] ?? '=';
        $compareValue = $condition['value'] ?? null;

        switch ($operator) {
            case '=':
            case '==':
                return $value == $compareValue;
            
            case '!=':
            case '<>':
                return $value != $compareValue;
            
            case '>':
                return $value > $compareValue;
            
            case '>=':
                return $value >= $compareValue;
            
            case '<':
                return $value < $compareValue;
            
            case '<=':
                return $value <= $compareValue;
            
            case 'in':
                return is_array($compareValue) && in_array($value, $compareValue);
            
            case 'not_in':
                return is_array($compareValue) && !in_array($value, $compareValue);
            
            case 'like':
                return is_string($value) && is_string($compareValue) && 
                       str_contains(strtolower($value), strtolower($compareValue));
            
            case 'not_like':
                return is_string($value) && is_string($compareValue) && 
                       !str_contains(strtolower($value), strtolower($compareValue));
            
            default:
                return false;
        }
    }

    /**
     * 评估自定义条件
     *
     * @param string $key
     * @param mixed $condition
     * @param array $context
     * @return bool
     */
    protected function evaluateCustomCondition(string $key, mixed $condition, array $context): bool
    {
        // 检查上下文中是否有对应的值
        if (!array_key_exists($key, $context)) {
            return false;
        }

        $contextValue = $context[$key];

        // 如果条件是数组且包含 operator，使用操作符评估
        if (is_array($condition) && isset($condition['operator'])) {
            return $this->evaluateOperatorCondition($contextValue, $condition);
        }

        // 简单相等比较
        if (is_scalar($condition)) {
            return $contextValue == $condition;
        }

        // 数组包含检查
        if (is_array($condition)) {
            return in_array($contextValue, $condition);
        }

        return false;
    }

    /**
     * 计算策略优先级
     *
     * @param PolicyStatement $statement
     * @param PolicyAssignment $assignment
     * @return int
     */
    protected function calculatePriority(PolicyStatement $statement, PolicyAssignment $assignment): int
    {
        $priority = 0;

        // 拒绝策略优先级更高
        if ($statement->isDeny()) {
            $priority += 1000;
        }

        // 更具体的资源匹配优先级更高
        $resources = $statement->getResources();
        if (!empty($resources) && !in_array('*', $resources, true)) {
            $priority += 100;
        }

        // 更具体的操作匹配优先级更高
        $actions = $statement->getActions();
        if (!empty($actions) && !in_array('*', $actions, true)) {
            $priority += 10;
        }

        // 有条件的策略优先级更高
        if (!empty($statement->getConditions())) {
            $priority += 1;
        }

        return $priority;
    }

    /**
     * 评估策略声明
     *
     * @param Collection $statements
     * @return bool
     */
    protected function evaluateStatements(Collection $statements): bool
    {
        // 如果没有匹配的策略，默认拒绝
        if ($statements->isEmpty()) {
            return false;
        }

        // 检查是否有拒绝策略（拒绝优先）
        foreach ($statements as $item) {
            $statement = $item['statement'];
            if ($statement->isDeny()) {
                return false;
            }
        }

        // 检查是否有允许策略
        foreach ($statements as $item) {
            $statement = $item['statement'];
            if ($statement->isAllow()) {
                return true;
            }
        }

        // 默认拒绝
        return false;
    }

    /**
     * 重写父类的超级管理员检查方法
     *
     * @param PermissionSubject $subject
     * @return bool
     */
    protected function isSuperAdmin(PermissionSubject $subject): bool
    {
        // 首先调用父类的检查（使用自定义检查器）
        if (parent::isSuperAdmin($subject)) {
            return true;
        }

        // 默认检查：是否有 super-admin 角色
        if ($subject->getSubjectType() === 'role' && $subject instanceof \Dybasedev\LunaPrototype\Permission\Models\Role) {
            return $subject->name === 'super-admin';
        }

        // 检查用户是否分配了 admin-full-access 策略
        if (method_exists($subject, 'hasPolicy')) {
            return $subject->hasPolicy('admin-full-access');
        }

        return false;
    }
}
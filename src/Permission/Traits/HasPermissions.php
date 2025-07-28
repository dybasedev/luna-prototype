<?php

namespace Dybasedev\LunaPrototype\Permission\Traits;

use Dybasedev\LunaPrototype\Permission\PermissionSubject;
use Dybasedev\LunaPrototype\Permission\LunaPermissionConfigure;
use Dybasedev\LunaPrototype\Permission\Models\PolicyAssignment;
use Dybasedev\LunaPrototype\Permission\Models\UserGroup;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * 权限功能 Trait
 * 
 * 为用户模型添加权限相关功能
 */
trait HasPermissions
{
    /**
     * 获取用户的策略分配
     *
     * @return HasMany
     */
    public function policyAssignments(): HasMany
    {
        return $this->hasMany(PolicyAssignment::class, 'subject_id')
            ->where('subject_type', hash_code($this->getSubjectType()));
    }

    /**
     * 获取用户所属的用户组
     *
     * @return BelongsToMany
     */
    public function permissionGroups(): BelongsToMany
    {
        $configure = app(LunaPermissionConfigure::class);
        $groupModel = $configure->userGroupContract ?? UserGroup::class;
        
        return $this->belongsToMany(
            $groupModel,
            'luna_permission_user_group_members',
            'user_id',
            'group_id'
        )->withTimestamps();
    }

    /**
     * 获取主体类型（实现 PermissionSubject 接口）
     *
     * @return string
     */
    public function getSubjectType(): string
    {
        return 'user';
    }

    /**
     * 获取主体ID（实现 PermissionSubject 接口）
     *
     * @return string|int
     */
    public function getSubjectId(): string|int
    {
        return $this->getKey();
    }

    /**
     * 获取主体标识符（实现 PermissionSubject 接口）
     *
     * @return string
     */
    public function getSubjectIdentifier(): string
    {
        return sprintf('%s:%s', $this->getSubjectType(), $this->getSubjectId());
    }

    /**
     * 获取主体显示名称（实现 PermissionSubject 接口）
     *
     * @return string
     */
    public function getSubjectDisplayName(): string
    {
        return $this->name ?? $this->email ?? (string) $this->getKey();
    }

    /**
     * 获取所有有效的策略分配（包括通过用户组继承的）
     *
     * @return Collection
     */
    public function getAllPolicyAssignments(): Collection
    {
        // 直接分配给用户的策略
        $directAssignments = $this->policyAssignments()->active()->with('policy')->get();

        // 通过用户组分配的策略
        $groupIds = $this->permissionGroups()->pluck('id');
        $groupAssignments = PolicyAssignment::query()
            ->where('subject_type', hash_code('group'))
            ->whereIn('subject_id', $groupIds)
            ->active()
            ->with('policy')
            ->get();

        return $directAssignments->merge($groupAssignments);
    }

    /**
     * 分配策略给用户
     *
     * @param \Dybasedev\LunaPrototype\Permission\Models\Policy|string $policy
     * @param array $options
     * @return PolicyAssignment
     */
    public function assignPolicy(\Dybasedev\LunaPrototype\Permission\Models\Policy|string $policy, array $options = []): PolicyAssignment
    {
        return PolicyAssignment::assign($policy, $this, $options);
    }

    /**
     * 撤销策略
     *
     * @param \Dybasedev\LunaPrototype\Permission\Models\Policy|string $policy
     * @return bool
     */
    public function revokePolicy(\Dybasedev\LunaPrototype\Permission\Models\Policy|string $policy): bool
    {
        if (is_string($policy)) {
            $policy = \Dybasedev\LunaPrototype\Permission\Models\Policy::findByName($policy);
        }

        if (!$policy) {
            return false;
        }

        return $this->policyAssignments()
            ->where('policy_id', $policy->id)
            ->delete() > 0;
    }

    /**
     * 检查是否有指定的策略
     *
     * @param string $policyName
     * @return bool
     */
    public function hasPolicy(string $policyName): bool
    {
        return $this->getEffectivePolicies()
            ->filter(fn($policy) => $policy->name === $policyName)
            ->isNotEmpty();
    }

    /**
     * 加入用户组
     *
     * @param \Dybasedev\LunaPrototype\Permission\Contracts\UserGroupContract|string $group
     * @return void
     */
    public function joinGroup(\Dybasedev\LunaPrototype\Permission\Contracts\UserGroupContract|string $group): void
    {
        if (is_string($group)) {
            $configure = app(LunaPermissionConfigure::class);
            $groupModel = $configure->userGroupContract ?? UserGroup::class;
            $group = $groupModel::query()->where('name', $group)->first();
        }

        if ($group) {
            $group->addMember($this);
        }
    }

    /**
     * 离开用户组
     *
     * @param \Dybasedev\LunaPrototype\Permission\Contracts\UserGroupContract|string $group
     * @return void
     */
    public function leaveGroup(\Dybasedev\LunaPrototype\Permission\Contracts\UserGroupContract|string $group): void
    {
        if (is_string($group)) {
            $configure = app(LunaPermissionConfigure::class);
            $groupModel = $configure->userGroupContract ?? UserGroup::class;
            $group = $groupModel::query()->where('name', $group)->first();
        }

        if ($group) {
            $group->removeMember($this);
        }
    }

    /**
     * 检查是否在指定用户组
     *
     * @param string $groupName
     * @return bool
     */
    public function inGroup(string $groupName): bool
    {
        return $this->permissionGroups()
            ->where('name', $groupName)
            ->exists();
    }

    /**
     * 获取有效的策略集合
     *
     * @return Collection
     */
    public function getEffectivePolicies(): Collection
    {
        $cacheKey = 'user_policies:' . $this->getSubjectIdentifier();
        
        return \Cache::remember($cacheKey, 3600, function () {
            return $this->getAllPolicyAssignments()
                ->map(fn($assignment) => $assignment->policy)
                ->filter()
                ->unique('id');
        });
    }
}
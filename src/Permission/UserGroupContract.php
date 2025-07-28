<?php

namespace Dybasedev\LunaPrototype\Permission;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 用户组契约
 * 
 * 业务系统可以自行实现用户组模型
 */
interface UserGroupContract
{
    /**
     * 获取组ID
     *
     * @return string|int
     */
    public function getGroupId(): string|int;

    /**
     * 获取组名称
     *
     * @return string
     */
    public function getGroupName(): string;

    /**
     * 获取组描述
     *
     * @return string|null
     */
    public function getGroupDescription(): ?string;

    /**
     * 获取组成员关系
     *
     * @return BelongsToMany
     */
    public function members(): BelongsToMany;

    /**
     * 检查用户是否在组内
     *
     * @param mixed $user
     * @return bool
     */
    public function hasMember(mixed $user): bool;

    /**
     * 添加组成员
     *
     * @param mixed $user
     * @return void
     */
    public function addMember(mixed $user): void;

    /**
     * 移除组成员
     *
     * @param mixed $user
     * @return void
     */
    public function removeMember(mixed $user): void;
}
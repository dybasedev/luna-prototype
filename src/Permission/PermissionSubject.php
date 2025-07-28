<?php

namespace Dybasedev\LunaPrototype\Permission;

/**
 * 可授权主体接口
 * 
 * 用户、角色、用户组等都可以作为授权主体
 */
interface PermissionSubject
{
    /**
     * 获取主体类型
     * 
     * @return string
     */
    public function getSubjectType(): string;

    /**
     * 获取主体ID
     *
     * @return string|int
     */
    public function getSubjectId(): string|int;

    /**
     * 获取主体标识符
     * 格式: type:id
     *
     * @return string
     */
    public function getSubjectIdentifier(): string;

    /**
     * 获取主体显示名称
     *
     * @return string
     */
    public function getSubjectDisplayName(): string;
}
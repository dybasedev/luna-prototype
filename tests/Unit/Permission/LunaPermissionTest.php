<?php

namespace Dybasedev\LunaPrototype\Tests\Unit\Permission;

use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Permission\LunaPermission;
use Dybasedev\LunaPrototype\Permission\LunaPermissionConfigure;
use Dybasedev\LunaPrototype\Permission\Models\Policy;
use Dybasedev\LunaPrototype\Permission\Models\PolicyAssignment;
use Dybasedev\LunaPrototype\Permission\Models\Role;
use Dybasedev\LunaPrototype\Permission\PermissionSubject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Dybasedev\LunaPrototype\Tests\TestCase;

class LunaPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected LunaPermission $permission;
    protected LunaPermissionConfigure $configure;

    protected function setUp(): void
    {
        parent::setUp();

        // 设置配置
        $this->configure = LunaPermissionConfigure::create();
        $this->app->instance(LunaPermissionConfigure::class, $this->configure);
        
        // 创建 LunaPermission 实例
        $this->permission = new LunaPermission($this->configure, Cache::store());
    }

    /** @test */
    public function 可以创建策略()
    {
        $statement = [
            'effect' => 'allow',
            'action' => ['read', 'write'],
            'resource' => 'posts:*',
        ];

        $policy = $this->permission->createPolicy('test-policy-create', $statement, 'Test Policy');

        $this->assertInstanceOf(Policy::class, $policy);
        $this->assertEquals('test-policy-create', $policy->name);
        $this->assertEquals('Test Policy', $policy->description);
        $this->assertNotNull($policy->current_version_id);
        
        // 验证缓存
        $cached = $this->permission->getPolicyByName('test-policy-create');
        $this->assertEquals($policy->id, $cached->id);
    }

    /** @test */
    public function 防止重复的策略名称()
    {
        $statement = ['effect' => 'allow', 'action' => 'read', 'resource' => 'posts'];
        
        $this->permission->createPolicy('test-policy-dup', $statement);

        $this->expectException(LunaException::class);
        $this->expectExceptionMessage('策略名称已存在');
        
        $this->permission->createPolicy('test-policy-dup', $statement);
    }

    /** @test */
    public function 可以更新策略()
    {
        $policy = $this->permission->createPolicy('test-policy-update', [
            'effect' => 'allow',
            'action' => 'read',
            'resource' => 'posts',
        ]);

        // 保存原始版本ID
        $originalVersionId = $policy->current_version_id;

        $newStatement = [
            'effect' => 'allow',
            'action' => ['read', 'write'],
            'resource' => 'posts:*',
        ];

        $updated = $this->permission->updatePolicy($policy, $newStatement, 'Added write permission');

        $this->assertEquals($policy->id, $updated->id);
        $this->assertNotEquals($originalVersionId, $updated->current_version_id);
        
        // 验证新版本内容
        $statement = $updated->getStatement();
        $this->assertEquals(['read', 'write'], $statement->getActions());
    }

    /** @test */
    public function 可以删除策略()
    {
        $policy = $this->permission->createPolicy('test-policy-delete', [
            'effect' => 'allow',
            'action' => 'read',
            'resource' => 'posts',
        ]);

        // 分配策略给角色
        $role = Role::create([
            'name' => 'editor-delete',
            'display_name' => 'Editor',
        ]);
        
        PolicyAssignment::assign($policy, $role);

        // 删除策略
        $result = $this->permission->deletePolicy($policy);
        $this->assertTrue($result);

        // 验证级联删除
        $this->assertDatabaseMissing('luna_permission_policies', ['id' => $policy->id]);
        $this->assertDatabaseMissing('luna_permission_policy_versions', ['policy_id' => $policy->id]);
        $this->assertDatabaseMissing('luna_permission_policy_assignments', ['policy_id' => $policy->id]);
        
        // 验证缓存清理
        $this->assertNull($this->permission->getPolicyByName('test-policy-delete'));
    }

    /** @test */
    public function 可以列出策略()
    {
        $this->permission->createPolicy('policy-1', [
            'effect' => 'allow',
            'action' => 'read',
            'resource' => 'posts',
        ]);
        
        $this->permission->createPolicy('policy-2', [
            'effect' => 'deny',
            'action' => 'delete',
            'resource' => 'posts',
        ]);

        $policies = $this->permission->listPolicies();
        
        $this->assertCount(2, $policies);
        $this->assertTrue($policies->pluck('name')->contains('policy-1'));
        $this->assertTrue($policies->pluck('name')->contains('policy-2'));
    }

    /** @test */
    public function 可以创建和管理角色()
    {
        $role = $this->permission->createRole('editor', 'Content Editor', 'Can edit content');

        $this->assertInstanceOf(Role::class, $role);
        $this->assertEquals('editor', $role->name);
        $this->assertEquals('Content Editor', $role->display_name);
        $this->assertFalse($role->is_system);

        // 获取角色
        $found = $this->permission->getRoleByName('editor');
        $this->assertEquals($role->id, $found->id);

        // 列出角色
        $roles = $this->permission->listRoles();
        $this->assertCount(1, $roles);
    }

    /** @test */
    public function 防止重复的角色名称()
    {
        $this->permission->createRole('editor-dup', 'Editor');

        $this->expectException(LunaException::class);
        $this->expectExceptionMessage('角色名称已存在');
        
        $this->permission->createRole('editor-dup', 'Another Editor');
    }

    /** @test */
    public function 防止删除系统角色()
    {
        $role = Role::createSystemRole('system-admin', 'System Admin');

        $this->expectException(LunaException::class);
        $this->expectExceptionMessage('系统角色不允许删除');
        
        $this->permission->deleteRole($role);
    }

    /** @test */
    public function 可以分配策略到主体()
    {
        $policy = $this->permission->createPolicy('read-posts', [
            'effect' => 'allow',
            'action' => 'read',
            'resource' => 'posts:*',
        ]);

        $role = $this->permission->createRole('reader-assign', 'Reader');

        $assignment = $this->permission->assignPolicy($role, $policy, [
            'conditions' => ['ip' => '192.168.1.0/24'],
        ]);

        $this->assertInstanceOf(PolicyAssignment::class, $assignment);
        $this->assertEquals($policy->id, $assignment->policy_id);
        $this->assertEquals(hash_code('role'), $assignment->subject_type);
        $this->assertEquals($role->id, $assignment->subject_id);
        $this->assertEquals(['ip' => '192.168.1.0/24'], $assignment->conditions);
    }

    /** @test */
    public function 防止重复的策略分配()
    {
        $policy = $this->permission->createPolicy('test-policy-dup-assign', [
            'effect' => 'allow',
            'action' => 'read',
            'resource' => 'posts',
        ]);

        $role = $this->permission->createRole('reader-dup-assign', 'Reader');
        
        $this->permission->assignPolicy($role, $policy);

        $this->expectException(LunaException::class);
        $this->expectExceptionMessage('策略已分配');
        
        $this->permission->assignPolicy($role, $policy);
    }

    /** @test */
    public function 允许重新分配过期的策略()
    {
        $policy = $this->permission->createPolicy('test-policy-expired', [
            'effect' => 'allow',
            'action' => 'read',
            'resource' => 'posts',
        ]);

        $role = $this->permission->createRole('reader-expired', 'Reader');
        
        // 分配一个已过期的策略
        $firstAssignment = $this->permission->assignPolicy($role, $policy, [
            'expires_at' => now()->subDay(),
        ]);

        // 验证第一次分配已过期
        $this->assertTrue($firstAssignment->isExpired());

        // 应该允许重新分配
        $assignment = $this->permission->assignPolicy($role, $policy);
        $this->assertNull($assignment->expires_at);
        
        // 验证旧的分配已被删除，新的分配已创建
        $this->assertNotEquals($firstAssignment->id, $assignment->id);
        $this->assertDatabaseMissing('luna_permission_policy_assignments', [
            'id' => $firstAssignment->id
        ]);
    }

    /** @test */
    public function 可以撤销策略()
    {
        $policy = $this->permission->createPolicy('test-policy-revoke', [
            'effect' => 'allow',
            'action' => 'read',
            'resource' => 'posts',
        ]);

        $role = $this->permission->createRole('reader-revoke', 'Reader');
        $this->permission->assignPolicy($role, $policy);

        $result = $this->permission->revokePolicy($role, $policy);
        $this->assertEquals(1, $result);

        // 验证已删除
        $this->assertDatabaseMissing('luna_permission_policy_assignments', [
            'policy_id' => $policy->id,
            'subject_id' => $role->id,
        ]);
    }

    /** @test */
    public function 分配时验证主体存在()
    {
        $policy = $this->permission->createPolicy('test-policy-validate', [
            'effect' => 'allow',
            'action' => 'read',
            'resource' => 'posts',
        ]);

        // 创建一个假的主体
        $fakeSubject = new class implements PermissionSubject {
            public function getSubjectType(): string { return 'role'; }
            public function getSubjectId(): string { return 'non-existent-id'; }
            public function getSubjectIdentifier(): string { return 'role:non-existent-id'; }
            public function getSubjectDisplayName(): string { return 'Fake'; }
        };

        $this->expectException(LunaException::class);
        $this->expectExceptionMessage('权限主体不存在');
        
        $this->permission->assignPolicy($fakeSubject, $policy);
    }

    /** @test */
    public function 处理权限检查()
    {
        $policy = $this->permission->createPolicy('posts-policy', [
            'effect' => 'allow',
            'action' => ['read', 'write'],
            'resource' => 'posts:*',
        ]);

        $role = $this->permission->createRole('editor-check', 'Editor');
        $this->permission->assignPolicy($role, $policy);

        // 模拟权限检查
        $result = $this->permission->check($role, 'read', 'posts:123');
        $this->assertTrue($result);

        $result = $this->permission->check($role, 'delete', 'posts:123');
        $this->assertFalse($result);
    }

    /** @test */
    public function 批量检查权限()
    {
        $policy1 = $this->permission->createPolicy('read-policy', [
            'effect' => 'allow',
            'action' => 'read',
            'resource' => 'posts:*',
        ]);

        $policy2 = $this->permission->createPolicy('write-policy', [
            'effect' => 'allow',
            'action' => 'write',
            'resource' => 'comments:*',
        ]);

        $role = $this->permission->createRole('user', 'User');
        $this->permission->assignPolicy($role, $policy1);
        $this->permission->assignPolicy($role, $policy2);

        $results = $this->permission->checkMany($role, [
            ['action' => 'read', 'resource' => 'posts:1'],
            ['action' => 'write', 'resource' => 'posts:1'],
            ['action' => 'write', 'resource' => 'comments:1'],
        ]);

        $this->assertTrue($results[0]['allowed']);  // Can read posts
        $this->assertFalse($results[1]['allowed']); // Cannot write posts
        $this->assertTrue($results[2]['allowed']);  // Can write comments
    }

    /** @test */
    public function 处理没有锁支持的缓存()
    {
        // 创建一个不支持锁的缓存 mock
        $cache = $this->createMock(\Illuminate\Contracts\Cache\Repository::class);
        $cache->method('get')->willReturn(null);
        $cache->method('remember')->willReturnCallback(function ($key, $ttl, $callback) {
            return $callback();
        });
        $cache->method('forget')->willReturn(true);

        $permission = new LunaPermission($this->configure, $cache);

        // 应该仍然能够创建策略
        $policy = $permission->createPolicy('test-policy-cache', [
            'effect' => 'allow',
            'action' => 'read',
            'resource' => 'posts',
        ]);

        $this->assertNotNull($policy);
    }

    /** @test */
    public function 注册资源()
    {
        $this->permission->registerResource('posts', [
            'description' => 'Blog posts',
            'actions' => ['create', 'read', 'update', 'delete'],
        ]);

        $this->permission->registerResources([
            'comments' => [
                'description' => 'Comments',
                'actions' => ['create', 'read', 'delete'],
            ],
            'users' => [
                'description' => 'Users',
                'actions' => ['read', 'update'],
            ],
        ]);

        // 通过 ResourceRegistry 验证
        $registry = $this->permission->getResourceRegistry();
        
        $posts = $registry->get('posts');
        $this->assertNotNull($posts);
        $this->assertEquals(['create', 'read', 'update', 'delete'], $posts->actions);
        
        $comments = $registry->get('comments');
        $this->assertNotNull($comments);
        $this->assertEquals(['create', 'read', 'delete'], $comments->actions);
    }
}
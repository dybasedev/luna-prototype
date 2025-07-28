<?php

namespace Tests\Unit\Permission;

use Dybasedev\LunaPrototype\Permission\Models\Policy;
use Dybasedev\LunaPrototype\Permission\Models\PolicyAssignment;
use Dybasedev\LunaPrototype\Permission\Models\Role;
use Dybasedev\LunaPrototype\Permission\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Dybasedev\LunaPrototype\Tests\TestCase;

class PolicyAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 运行迁移
        $this->loadMigrationsFrom(__DIR__ . '/../../../src/Permission/migrations');
    }

    /** @test */
    public function it_can_create_assignment()
    {
        $policy = Policy::create(['name' => 'test-policy']);
        $role = Role::create(['name' => 'editor', 'display_name' => 'Editor']);

        $assignment = PolicyAssignment::create([
            'policy_id' => $policy->id,
            'subject_type' => hash_code('role'),
            'subject_id' => $role->id,
            'conditions' => ['ip' => '192.168.1.0/24'],
            'expires_at' => now()->addDays(30),
        ]);

        $this->assertEquals($policy->id, $assignment->policy_id);
        $this->assertEquals(hash_code('role'), $assignment->subject_type);
        $this->assertEquals($role->id, $assignment->subject_id);
        $this->assertEquals(['ip' => '192.168.1.0/24'], $assignment->conditions);
        $this->assertTrue($assignment->expires_at->isFuture());
    }

    /** @test */
    public function it_can_assign_using_static_method()
    {
        $policy = Policy::create(['name' => 'read-policy']);
        $policy->createVersion([
            'effect' => 'allow',
            'action' => 'read',
            'resource' => 'posts',
        ]);

        $role = Role::create(['name' => 'reader', 'display_name' => 'Reader']);

        $assignment = PolicyAssignment::assign($policy, $role, [
            'conditions' => ['time' => 'business-hours'],
        ]);

        $this->assertNotNull($assignment);
        $this->assertEquals($policy->id, $assignment->policy_id);
        $this->assertEquals(hash_code('role'), $assignment->subject_type);
        $this->assertEquals($role->id, $assignment->subject_id);
    }

    /** @test */
    public function it_can_assign_using_policy_name()
    {
        $policy = Policy::create(['name' => 'write-policy']);
        $role = Role::create(['name' => 'writer', 'display_name' => 'Writer']);

        $assignment = PolicyAssignment::assign('write-policy', $role);

        $this->assertEquals($policy->id, $assignment->policy_id);
    }

    /** @test */
    public function it_throws_exception_for_invalid_policy()
    {
        $role = Role::create(['name' => 'user', 'display_name' => 'User']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Policy not found');

        PolicyAssignment::assign('non-existent-policy', $role);
    }

    /** @test */
    public function it_has_policy_relationship()
    {
        $policy = Policy::create(['name' => 'test-policy']);
        $role = Role::create(['name' => 'admin', 'display_name' => 'Admin']);

        $assignment = PolicyAssignment::assign($policy, $role);

        $this->assertInstanceOf(Policy::class, $assignment->policy);
        $this->assertEquals($policy->id, $assignment->policy->id);
    }

    /** @test */
    public function it_can_get_subject_model()
    {
        $policy = Policy::create(['name' => 'test-policy']);
        
        // 测试角色主体
        $role = Role::create(['name' => 'editor', 'display_name' => 'Editor']);
        $assignment = PolicyAssignment::assign($policy, $role);
        
        $subject = $assignment->getSubject();
        $this->assertInstanceOf(Role::class, $subject);
        $this->assertEquals($role->id, $subject->id);

        // 测试用户组主体
        $group = UserGroup::create(['name' => 'admins']);
        $assignment2 = PolicyAssignment::assign($policy, $group);
        
        $subject2 = $assignment2->getSubject();
        $this->assertInstanceOf(UserGroup::class, $subject2);
        $this->assertEquals($group->id, $subject2->id);
    }

    /** @test */
    public function it_can_scope_by_subject()
    {
        $policy = Policy::create(['name' => 'test-policy']);
        $role1 = Role::create(['name' => 'role1', 'display_name' => 'Role 1']);
        $role2 = Role::create(['name' => 'role2', 'display_name' => 'Role 2']);
        $group = UserGroup::create(['name' => 'group1']);

        PolicyAssignment::assign($policy, $role1);
        PolicyAssignment::assign($policy, $role2);
        PolicyAssignment::assign($policy, $group);

        // 查询特定主体的分配
        $roleAssignments = PolicyAssignment::bySubject('role', $role1->id)->get();
        $this->assertCount(1, $roleAssignments);
        $this->assertEquals($role1->id, $roleAssignments->first()->subject_id);

        // 查询所有角色的分配
        $allRoleAssignments = PolicyAssignment::query()
            ->where('subject_type', hash_code('role'))
            ->get();
        $this->assertCount(2, $allRoleAssignments);
    }

    /** @test */
    public function it_can_scope_active_assignments()
    {
        $policy1 = Policy::create(['name' => 'test-policy-1']);
        $policy2 = Policy::create(['name' => 'test-policy-2']);
        $policy3 = Policy::create(['name' => 'test-policy-3']);
        $role = Role::create(['name' => 'user', 'display_name' => 'User']);

        // 创建不同过期时间的分配
        PolicyAssignment::assign($policy1, $role, [
            'expires_at' => now()->addDays(30), // 未过期
        ]);

        PolicyAssignment::assign($policy2, $role, [
            'expires_at' => now()->subDays(1), // 已过期
        ]);

        PolicyAssignment::assign($policy3, $role, [
            'expires_at' => null, // 永不过期
        ]);

        $active = PolicyAssignment::active()->get();
        $this->assertCount(2, $active); // 只有未过期和永不过期的
    }

    /** @test */
    public function it_can_check_expiration()
    {
        $policy1 = Policy::create(['name' => 'test-policy-exp1']);
        $policy2 = Policy::create(['name' => 'test-policy-exp2']);
        $policy3 = Policy::create(['name' => 'test-policy-exp3']);
        $role = Role::create(['name' => 'temp', 'display_name' => 'Temporary']);

        $expired = PolicyAssignment::assign($policy1, $role, [
            'expires_at' => now()->subHour(),
        ]);
        $this->assertTrue($expired->isExpired());

        $active = PolicyAssignment::assign($policy2, $role, [
            'expires_at' => now()->addHour(),
        ]);
        $this->assertFalse($active->isExpired());

        $permanent = PolicyAssignment::assign($policy3, $role);
        $this->assertFalse($permanent->isExpired());
    }

    /** @test */
    public function it_handles_unknown_subject_types()
    {
        $assignment = new PolicyAssignment([
            'policy_id' => 'test',
            'subject_type' => 'unknown',
            'subject_id' => '123',
        ]);

        $subject = $assignment->getSubject();
        $this->assertNull($subject);
    }
}
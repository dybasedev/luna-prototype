<?php

namespace Tests\Unit\Permission;

use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Permission\Models\Role;
use Dybasedev\LunaPrototype\Permission\Models\PolicyAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Dybasedev\LunaPrototype\Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 运行迁移
        $this->loadMigrationsFrom(__DIR__ . '/../../../src/Permission/migrations');
    }

    /** @test */
    public function it_can_create_role()
    {
        $role = Role::create([
            'name' => 'editor',
            'display_name' => 'Content Editor',
            'description' => 'Can edit content',
            'metadata' => ['level' => 2],
        ]);

        $this->assertEquals('editor', $role->name);
        $this->assertEquals('Content Editor', $role->display_name);
        $this->assertEquals(['level' => 2], $role->metadata);
        $this->assertFalse($role->is_system);
    }

    /** @test */
    public function it_implements_permission_subject()
    {
        $role = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrator',
        ]);

        $this->assertEquals('role', $role->getSubjectType());
        $this->assertEquals($role->id, $role->getSubjectId());
        $this->assertEquals('role:' . $role->id, $role->getSubjectIdentifier());
        $this->assertEquals('Administrator', $role->getSubjectDisplayName());
    }

    /** @test */
    public function it_can_find_by_name()
    {
        $role = Role::create([
            'name' => 'editor',
            'display_name' => 'Editor',
        ]);

        $found = Role::findByName('editor');
        $this->assertNotNull($found);
        $this->assertEquals($role->id, $found->id);

        $notFound = Role::findByName('non-existent');
        $this->assertNull($notFound);
    }

    /** @test */
    public function it_can_create_system_role()
    {
        $role = Role::createSystemRole('super-admin', 'Super Administrator', [
            'description' => 'Has all permissions',
            'metadata' => ['protected' => true],
        ]);

        $this->assertEquals('super-admin', $role->name);
        $this->assertEquals('Super Administrator', $role->display_name);
        $this->assertTrue($role->is_system);
        $this->assertEquals('Has all permissions', $role->description);
        $this->assertEquals(['protected' => true], $role->metadata);
    }

    /** @test */
    public function it_prevents_deleting_system_roles()
    {
        $role = Role::createSystemRole('system', 'System Role');

        $this->expectException(LunaException::class);
        $this->expectExceptionMessage('系统角色不允许删除');

        $role->delete();
    }

    /** @test */
    public function it_cascades_delete_policy_assignments()
    {
        $role = Role::create([
            'name' => 'temp-role',
            'display_name' => 'Temporary Role',
        ]);

        // 创建一些策略分配（模拟）
        PolicyAssignment::create([
            'policy_id' => 'test-policy-id',
            'subject_type' => hash_code('role'),
            'subject_id' => $role->id,
        ]);

        $this->assertDatabaseHas('luna_permission_policy_assignments', [
            'subject_type' => hash_code('role'),
            'subject_id' => $role->id,
        ]);

        // 删除角色
        $role->delete();

        // 验证级联删除
        $this->assertDatabaseMissing('luna_permission_policy_assignments', [
            'subject_type' => hash_code('role'),
            'subject_id' => $role->id,
        ]);
    }

    /** @test */
    public function it_has_policy_assignments_relationship()
    {
        $role = Role::create([
            'name' => 'user',
            'display_name' => 'User',
        ]);

        PolicyAssignment::create([
            'policy_id' => 'policy-1',
            'subject_type' => hash_code('role'),
            'subject_id' => $role->id,
        ]);

        PolicyAssignment::create([
            'policy_id' => 'policy-2',
            'subject_type' => hash_code('role'),
            'subject_id' => $role->id,
        ]);

        // 不应该包含其他类型的分配
        PolicyAssignment::create([
            'policy_id' => 'policy-3',
            'subject_type' => hash_code('user'),
            'subject_id' => $role->id,
        ]);

        $assignments = $role->policyAssignments;
        $this->assertCount(2, $assignments);
        $this->assertTrue($assignments->pluck('policy_id')->contains('policy-1'));
        $this->assertTrue($assignments->pluck('policy_id')->contains('policy-2'));
        $this->assertFalse($assignments->pluck('policy_id')->contains('policy-3'));
    }
}
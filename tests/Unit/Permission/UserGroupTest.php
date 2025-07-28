<?php

namespace Tests\Unit\Permission;

use Dybasedev\LunaPrototype\Permission\Models\UserGroup;
use Dybasedev\LunaPrototype\Permission\Models\PolicyAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Dybasedev\LunaPrototype\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;

class UserGroupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 运行迁移
        $this->loadMigrationsFrom(__DIR__ . '/../../../src/Permission/migrations');
        
        // 设置用户模型配置
        $configure = \Dybasedev\LunaPrototype\Permission\LunaPermissionConfigure::create();
        $this->app->instance(\Dybasedev\LunaPrototype\Permission\LunaPermissionConfigure::class, $configure);
    }

    /** @test */
    public function it_can_create_user_group()
    {
        $group = UserGroup::create([
            'name' => 'administrators',
            'description' => 'System administrators',
            'metadata' => ['level' => 'high'],
        ]);

        $this->assertEquals('administrators', $group->name);
        $this->assertEquals('System administrators', $group->description);
        $this->assertEquals(['level' => 'high'], $group->metadata);
    }

    /** @test */
    public function it_implements_permission_subject()
    {
        $group = UserGroup::create([
            'name' => 'editors',
            'description' => 'Content editors',
        ]);

        $this->assertEquals('group', $group->getSubjectType());
        $this->assertEquals($group->id, $group->getSubjectId());
        $this->assertEquals('group:' . $group->id, $group->getSubjectIdentifier());
        $this->assertEquals('editors', $group->getSubjectDisplayName());
    }

    /** @test */
    public function it_implements_user_group_contract()
    {
        $group = UserGroup::create([
            'name' => 'moderators',
            'description' => 'Forum moderators',
        ]);

        $this->assertEquals($group->id, $group->getGroupId());
        $this->assertEquals('moderators', $group->getGroupName());
        $this->assertEquals('Forum moderators', $group->getGroupDescription());
    }

    /** @test */
    public function it_can_manage_members()
    {
        $group = UserGroup::create(['name' => 'team']);

        // 创建模拟用户
        $user1 = $this->createMockUser('user-1');
        $user2 = $this->createMockUser('user-2');

        // 添加成员
        $group->addMember($user1);
        $group->addMember($user2);

        $this->assertTrue($group->hasMember($user1));
        $this->assertTrue($group->hasMember($user2));

        // 验证关系
        $memberCount = \DB::table('luna_permission_user_group_members')
            ->where('group_id', $group->id)
            ->count();
        $this->assertEquals(2, $memberCount);

        // 移除成员
        $group->removeMember($user1);
        $this->assertFalse($group->hasMember($user1));
        $this->assertTrue($group->hasMember($user2));
    }

    /** @test */
    public function it_prevents_duplicate_members()
    {
        $group = UserGroup::create(['name' => 'team']);
        $user = $this->createMockUser('user-1');

        $group->addMember($user);
        $group->addMember($user); // 应该被忽略

        $memberCount = \DB::table('luna_permission_user_group_members')
            ->where('group_id', $group->id)
            ->count();
        $this->assertEquals(1, $memberCount);
    }

    /** @test */
    public function it_cascades_delete()
    {
        $group = UserGroup::create(['name' => 'temp-group']);
        $user = $this->createMockUser('user-1');
        
        // 添加成员
        $group->addMember($user);

        // 创建策略分配
        PolicyAssignment::create([
            'policy_id' => 'test-policy',
            'subject_type' => hash_code('group'),
            'subject_id' => $group->id,
        ]);

        // 验证数据存在
        $this->assertDatabaseHas('luna_permission_user_group_members', [
            'group_id' => $group->id,
            'user_id' => $user->getKey(),
        ]);
        $this->assertDatabaseHas('luna_permission_policy_assignments', [
            'subject_type' => hash_code('group'),
            'subject_id' => $group->id,
        ]);

        // 删除组
        $group->delete();

        // 验证级联删除
        $this->assertDatabaseMissing('luna_permission_user_group_members', [
            'group_id' => $group->id,
        ]);
        $this->assertDatabaseMissing('luna_permission_policy_assignments', [
            'subject_type' => hash_code('group'),
            'subject_id' => $group->id,
        ]);
    }

    /** @test */
    public function it_has_policy_assignments_relationship()
    {
        $group = UserGroup::create(['name' => 'reviewers']);

        PolicyAssignment::create([
            'policy_id' => 'policy-1',
            'subject_type' => hash_code('group'),
            'subject_id' => $group->id,
        ]);

        PolicyAssignment::create([
            'policy_id' => 'policy-2',
            'subject_type' => hash_code('group'),
            'subject_id' => $group->id,
        ]);

        $assignments = $group->policyAssignments;
        $this->assertCount(2, $assignments);
    }

    /**
     * 创建模拟用户
     */
    protected function createMockUser(string $id)
    {
        $user = new class extends Model {
            protected $table = 'users';
            public $incrementing = false;
            protected $keyType = 'string';
            
            public function getKey()
            {
                return $this->id;
            }
        };
        
        $user->id = $id;
        $user->exists = true; // 假装已存在
        
        return $user;
    }
}
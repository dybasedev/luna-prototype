<?php

namespace Dybasedev\LunaPrototype\Tests\Unit\Permission;

use Dybasedev\LunaPrototype\Permission\Models\Policy;
use Dybasedev\LunaPrototype\Permission\Models\PolicyAssignment;
use Dybasedev\LunaPrototype\Permission\Models\UserGroup;
use Dybasedev\LunaPrototype\Permission\PermissionSubject;
use Dybasedev\LunaPrototype\Permission\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Dybasedev\LunaPrototype\Tests\TestCase;

class HasPermissionsTraitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 运行迁移
        $this->loadMigrationsFrom(__DIR__ . '/../../../src/Permission/migrations');
        
        // 创建用户表用于测试
        $this->createUsersTable();
    }

    protected function createUsersTable()
    {
        \Schema::create('users', function ($table) {
            $table->string('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });
    }

    /** @test */
    public function 实现权限主体接口()
    {
        $user = $this->createTestUser('user-1', 'John Doe', 'john@example.com');

        $this->assertEquals('user', $user->getSubjectType());
        $this->assertEquals('user-1', $user->getSubjectId());
        $this->assertEquals('user:user-1', $user->getSubjectIdentifier());
        $this->assertEquals('John Doe', $user->getSubjectDisplayName());
    }

    /** @test */
    public function 使用回退显示名称()
    {
        // 没有 name 的用户
        $user1 = $this->createTestUser('user-1', null, 'user@example.com');
        $this->assertEquals('user@example.com', $user1->getSubjectDisplayName());

        // 没有 name 和 email 的用户
        $user2 = $this->createTestUser('user-2', null, null);
        $this->assertEquals('user-2', $user2->getSubjectDisplayName());
    }

    /** @test */
    public function 拥有策略分配关系()
    {
        $user = $this->createTestUser('user-1', 'Test User');
        $policy = Policy::create(['name' => 'user-policy']);

        PolicyAssignment::assign($policy, $user);

        $assignments = $user->policyAssignments;
        $this->assertCount(1, $assignments);
        $this->assertEquals($policy->id, $assignments->first()->policy_id);
    }

    /** @test */
    public function 拥有权限组关系()
    {
        $user = $this->createTestUser('user-1', 'Test User');
        
        $group1 = UserGroup::create(['name' => 'editors']);
        $group2 = UserGroup::create(['name' => 'reviewers']);
        
        $group1->addMember($user);
        $group2->addMember($user);

        $groups = $user->permissionGroups;
        $this->assertCount(2, $groups);
        $this->assertTrue($groups->pluck('name')->contains('editors'));
        $this->assertTrue($groups->pluck('name')->contains('reviewers'));
    }

    /** @test */
    public function 获取所有策略分配包括组()
    {
        $user = $this->createTestUser('user-1', 'Test User');
        
        // 直接分配给用户的策略
        $userPolicy = Policy::create(['name' => 'user-policy']);
        PolicyAssignment::assign($userPolicy, $user);

        // 通过组分配的策略
        $group = UserGroup::create(['name' => 'admins']);
        $group->addMember($user);
        
        $groupPolicy = Policy::create(['name' => 'group-policy']);
        PolicyAssignment::assign($groupPolicy, $group);

        // 获取所有分配
        $allAssignments = $user->getAllPolicyAssignments();
        
        $this->assertCount(2, $allAssignments);
        $policyIds = $allAssignments->pluck('policy_id')->map(fn($id) => (string)$id)->toArray();
        $this->assertContains((string)$userPolicy->id, $policyIds);
        $this->assertContains((string)$groupPolicy->id, $policyIds);
    }

    /** @test */
    public function 过滤过期的分配()
    {
        $user = $this->createTestUser('user-1', 'Test User');
        $policy1 = Policy::create(['name' => 'temp-policy-expired']);
        $policy2 = Policy::create(['name' => 'temp-policy-valid']);

        // 创建过期的分配
        PolicyAssignment::create([
            'policy_id' => $policy1->id,
            'subject_type' => hash_code('user'),
            'subject_id' => $user->id,
            'expires_at' => now()->subDay(),
        ]);

        // 创建有效的分配
        PolicyAssignment::assign($policy2, $user, [
            'expires_at' => now()->addDay(),
        ]);

        $assignments = $user->getAllPolicyAssignments();
        $this->assertCount(1, $assignments);
        $this->assertTrue($assignments->first()->expires_at->isFuture());
        $this->assertEquals($policy2->id, $assignments->first()->policy_id);
    }

    /** @test */
    public function 获取有效的策略()
    {
        $user = $this->createTestUser('user-1', 'Test User');
        
        $policy1 = Policy::create(['name' => 'policy-1']);
        $policy1->createVersion([
            'effect' => 'allow',
            'action' => 'read',
            'resource' => 'posts',
        ]);
        
        $policy2 = Policy::create(['name' => 'policy-2']);
        $policy2->createVersion([
            'effect' => 'allow',
            'action' => 'write',
            'resource' => 'posts',
        ]);

        PolicyAssignment::assign($policy1, $user);
        PolicyAssignment::assign($policy2, $user);

        $policies = $user->getEffectivePolicies();
        
        $this->assertCount(2, $policies);
        $this->assertTrue($policies->pluck('name')->contains('policy-1'));
        $this->assertTrue($policies->pluck('name')->contains('policy-2'));
    }

    /** @test */
    public function 检查用户是否拥有特定策略()
    {
        $user = $this->createTestUser('user-haspolicy', 'Test User');
        
        $policy = Policy::create(['name' => 'read-policy']);
        PolicyAssignment::assign($policy, $user);

        $this->assertTrue($user->hasPolicy('read-policy'));
        $this->assertFalse($user->hasPolicy('write-policy'));

        // 通过组分配的策略
        $group = UserGroup::create(['name' => 'readers']);
        $group->addMember($user);
        
        $groupPolicy = Policy::create(['name' => 'group-read-policy']);
        PolicyAssignment::assign($groupPolicy, $group);

        // 清除缓存以确保获取最新的策略
        \Cache::forget('user_policies:' . $user->getSubjectIdentifier());
        
        $this->assertTrue($user->hasPolicy('group-read-policy'));
    }

    /** @test */
    public function 缓存策略检查()
    {
        $user = $this->createTestUser('user-cache', 'Test User');
        $policy = Policy::create(['name' => 'cached-policy']);
        PolicyAssignment::assign($policy, $user);

        // 第一次调用
        $this->assertTrue($user->hasPolicy('cached-policy'));
        
        // 第二次调用应该使用缓存
        $this->assertTrue($user->hasPolicy('cached-policy'));
        
        // 验证缓存键存在
        $cacheKey = 'user_policies:' . $user->getSubjectIdentifier();
        $this->assertNotNull(\Cache::get($cacheKey));
    }

    /**
     * 创建测试用户
     */
    protected function createTestUser($id, $name = null, $email = null)
    {
        return TestHasPermissionsUser::create([
            'id' => $id,
            'name' => $name,
            'email' => $email,
        ]);
    }
}

/**
 * 测试用户模型
 */
class TestHasPermissionsUser extends Model implements PermissionSubject
{
    use HasPermissions;

    protected $table = 'users';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'name', 'email'];
}
<?php

namespace Tests\Unit\Permission;

use Dybasedev\LunaPrototype\Permission\Models\Policy;
use Dybasedev\LunaPrototype\Permission\Models\PolicyStatement;
use Dybasedev\LunaPrototype\Permission\Models\PolicyVersion;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Dybasedev\LunaPrototype\Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 运行权限模块的迁移
        $this->loadMigrationsFrom(__DIR__ . '/../../../src/Permission/migrations');
    }

    /** @test */
    public function it_can_create_a_policy()
    {
        $policy = Policy::create([
            'name' => 'test-policy',
            'description' => 'Test Policy',
        ]);

        $this->assertDatabaseHas('luna_permission_policies', [
            'name' => 'test-policy',
            'description' => 'Test Policy',
        ]);

        $this->assertNotNull($policy->id);
        $this->assertEquals('test-policy', $policy->name);
    }

    /** @test */
    public function it_can_create_policy_version()
    {
        $policy = Policy::create([
            'name' => 'test-policy',
            'description' => 'Test Policy',
        ]);

        $statement = [
            'effect' => 'allow',
            'action' => ['read', 'write'],
            'resource' => 'posts:*',
        ];

        $policy->createVersion($statement, 'Initial version');

        $this->assertNotNull($policy->current_version_id);
        $this->assertDatabaseHas('luna_permission_policy_versions', [
            'policy_id' => $policy->id,
            'comment' => 'Initial version',
        ]);

        // 验证版本内容
        $version = $policy->current;
        $this->assertInstanceOf(PolicyVersion::class, $version);
        $this->assertEquals($statement, $version->statement);
    }

    /** @test */
    public function it_validates_policy_statement()
    {
        $policy = Policy::create([
            'name' => 'test-policy',
        ]);

        // 无效的 effect
        $this->expectException(LunaException::class);
        $this->expectExceptionMessage('策略效果必须是 allow 或 deny');

        $policy->createVersion([
            'effect' => 'invalid',
            'action' => 'read',
            'resource' => 'posts',
        ]);
    }

    /** @test */
    public function it_requires_action_or_not_action()
    {
        $policy = Policy::create([
            'name' => 'test-policy',
        ]);

        $this->expectException(LunaException::class);
        $this->expectExceptionMessage('策略必须指定 action 或 not_action');

        $policy->createVersion([
            'effect' => 'allow',
            'resource' => 'posts',
        ]);
    }

    /** @test */
    public function it_requires_resource()
    {
        $policy = Policy::create([
            'name' => 'test-policy',
        ]);

        $this->expectException(LunaException::class);
        $this->expectExceptionMessage('策略必须指定 resource');

        $policy->createVersion([
            'effect' => 'allow',
            'action' => 'read',
        ]);
    }

    /** @test */
    public function it_can_find_policy_by_name()
    {
        $policy = Policy::create([
            'name' => 'test-policy',
            'description' => 'Test Policy',
        ]);

        $found = Policy::findByName('test-policy');
        
        $this->assertNotNull($found);
        $this->assertEquals($policy->id, $found->id);
        
        $notFound = Policy::findByName('non-existent');
        $this->assertNull($notFound);
    }

    /** @test */
    public function it_can_switch_versions()
    {
        $policy = Policy::create([
            'name' => 'test-policy',
        ]);

        // 创建第一个版本
        $statement1 = [
            'effect' => 'allow',
            'action' => 'read',
            'resource' => 'posts',
        ];
        $policy->createVersion($statement1, 'Version 1');
        $version1Id = $policy->current_version_id;

        // 创建第二个版本
        $statement2 = [
            'effect' => 'allow',
            'action' => ['read', 'write'],
            'resource' => 'posts',
        ];
        $policy->createVersion($statement2, 'Version 2');
        $version2Id = $policy->current_version_id;

        // 验证当前是版本2
        $this->assertEquals($version2Id, $policy->current_version_id);
        $this->assertEquals($statement2, $policy->getStatement()->toArray());

        // 切换回版本1
        $result = $policy->applyVersion($version1Id);
        $this->assertTrue($result);
        
        $policy->refresh();
        $this->assertEquals($version1Id, $policy->current_version_id);
        $this->assertEquals($statement1, $policy->getStatement()->toArray());
    }

    /** @test */
    public function it_returns_null_statement_when_no_version()
    {
        $policy = Policy::create([
            'name' => 'test-policy',
        ]);

        $this->assertNull($policy->getStatement());
    }

    /** @test */
    public function it_can_query_by_name_scope()
    {
        Policy::create(['name' => 'policy-1']);
        Policy::create(['name' => 'policy-2']);
        Policy::create(['name' => 'policy-3']);

        // 单个名称查询
        $policies = Policy::byName('policy-1')->get();
        $this->assertCount(1, $policies);
        $this->assertEquals('policy-1', $policies->first()->name);

        // 多个名称查询
        $policies = Policy::byName(['policy-1', 'policy-3'])->get();
        $this->assertCount(2, $policies);
        $this->assertTrue($policies->pluck('name')->contains('policy-1'));
        $this->assertTrue($policies->pluck('name')->contains('policy-3'));
    }

    /** @test */
    public function version_control_creates_unique_hashes()
    {
        $policy = Policy::create([
            'name' => 'test-policy',
        ]);

        $statement = [
            'effect' => 'allow',
            'action' => 'read',
            'resource' => 'posts',
        ];

        // 创建两个相同内容的版本
        $policy->createVersion($statement, 'Version 1');
        $version1 = $policy->current_version_id;
        
        // 由于 VersionControl trait 的实现，相同内容会被忽略
        // 所以我们需要稍微修改内容
        $statement['action'] = ['read'];
        $policy->createVersion($statement, 'Version 2');
        $version2 = $policy->current_version_id;

        $this->assertNotEquals($version1, $version2);
    }
}
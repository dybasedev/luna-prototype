<?php

namespace Tests\Unit\Permission;

use Dybasedev\LunaPrototype\Permission\Models\PolicyStatement;
use Dybasedev\LunaPrototype\Permission\Models\PolicyStatementBuilder;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Tests\TestCase;

class PolicyStatementTest extends TestCase
{
    /** @test */
    public function it_parses_statement_correctly()
    {
        $data = [
            'effect' => 'allow',
            'action' => ['read', 'write'],
            'resource' => ['posts:*', 'comments:*'],
            'condition' => ['ip' => '192.168.1.0/24'],
            'principal' => ['user:123', 'role:admin'],
        ];

        $statement = new PolicyStatement($data);

        $this->assertEquals('allow', $statement->getEffect());
        $this->assertTrue($statement->isAllow());
        $this->assertFalse($statement->isDeny());
        $this->assertEquals(['read', 'write'], $statement->getActions());
        $this->assertEquals(['posts:*', 'comments:*'], $statement->getResources());
        $this->assertEquals(['ip' => '192.168.1.0/24'], $statement->getConditions());
        $this->assertEquals(['user:123', 'role:admin'], $statement->getPrincipals());
    }

    /** @test */
    public function it_defaults_to_deny_effect()
    {
        $statement = new PolicyStatement([]);
        
        $this->assertEquals(PolicyStatement::EFFECT_DENY, $statement->getEffect());
        $this->assertTrue($statement->isDeny());
        $this->assertFalse($statement->isAllow());
    }

    /** @test */
    public function it_wraps_single_values_in_arrays()
    {
        $statement = new PolicyStatement([
            'action' => 'read',
            'resource' => 'posts',
            'principal' => 'user:123',
        ]);

        $this->assertEquals(['read'], $statement->getActions());
        $this->assertEquals(['posts'], $statement->getResources());
        $this->assertEquals(['user:123'], $statement->getPrincipals());
    }

    /** @test */
    public function it_matches_actions_correctly()
    {
        // 简单匹配
        $statement = new PolicyStatement(['action' => 'read']);
        $this->assertTrue($statement->matchAction('read'));
        $this->assertFalse($statement->matchAction('write'));

        // 通配符匹配
        $statement = new PolicyStatement(['action' => 'posts:*']);
        $this->assertTrue($statement->matchAction('posts:read'));
        $this->assertTrue($statement->matchAction('posts:write'));
        $this->assertFalse($statement->matchAction('comments:read'));

        // 多个操作
        $statement = new PolicyStatement(['action' => ['read', 'write']]);
        $this->assertTrue($statement->matchAction('read'));
        $this->assertTrue($statement->matchAction('write'));
        $this->assertFalse($statement->matchAction('delete'));
    }

    /** @test */
    public function it_handles_not_actions()
    {
        $statement = new PolicyStatement([
            'not_action' => ['delete', 'admin:*']
        ]);

        $this->assertTrue($statement->matchAction('read'));
        $this->assertTrue($statement->matchAction('write'));
        $this->assertFalse($statement->matchAction('delete'));
        $this->assertFalse($statement->matchAction('admin:users'));
    }

    /** @test */
    public function it_matches_resources_with_patterns()
    {
        $statement = new PolicyStatement(['resource' => 'posts:*:comments']);
        
        $this->assertTrue($statement->matchResource('posts:123:comments'));
        $this->assertTrue($statement->matchResource('posts:abc:comments'));
        $this->assertFalse($statement->matchResource('posts:123'));
        $this->assertFalse($statement->matchResource('users:123:comments'));

        // 多个资源
        $statement = new PolicyStatement(['resource' => ['posts:*', 'users:profile']]);
        $this->assertTrue($statement->matchResource('posts:123'));
        $this->assertTrue($statement->matchResource('users:profile'));
        $this->assertFalse($statement->matchResource('users:settings'));
    }

    /** @test */
    public function it_matches_principals()
    {
        $statement = new PolicyStatement(['principal' => ['user:123', 'role:admin']]);
        
        $this->assertTrue($statement->matchPrincipal('user:123'));
        $this->assertTrue($statement->matchPrincipal('role:admin'));
        $this->assertFalse($statement->matchPrincipal('user:456'));

        // 通配符主体
        $statement = new PolicyStatement(['principal' => '*']);
        $this->assertTrue($statement->matchPrincipal('user:123'));
        $this->assertTrue($statement->matchPrincipal('role:any'));
    }

    /** @test */
    public function it_validates_required_fields()
    {
        // 有效的声明
        $statement = new PolicyStatement([
            'effect' => 'allow',
            'action' => 'read',
            'resource' => 'posts',
        ]);
        
        $this->assertTrue($statement->validate());
    }

    /** @test */
    public function it_validates_effect_values()
    {
        $statement = new PolicyStatement([
            'effect' => 'invalid',
            'action' => 'read',
            'resource' => 'posts',
        ]);

        $this->expectException(LunaException::class);
        $statement->validate();
    }

    /** @test */
    public function it_allows_not_action_without_action()
    {
        $statement = new PolicyStatement([
            'effect' => 'allow',
            'not_action' => 'delete',
            'resource' => 'posts',
        ]);

        $this->assertTrue($statement->validate());
    }

    /** @test */
    public function it_creates_allow_statement()
    {
        $statement = PolicyStatement::allow('read', 'posts', [
            'condition' => ['ip' => '192.168.1.1']
        ]);

        $this->assertEquals('allow', $statement['effect']);
        $this->assertEquals('read', $statement['action']);
        $this->assertEquals('posts', $statement['resource']);
        $this->assertEquals(['ip' => '192.168.1.1'], $statement['condition']);
    }

    /** @test */
    public function it_creates_deny_statement()
    {
        $statement = PolicyStatement::deny(['delete', 'admin:*'], '*');

        $this->assertEquals('deny', $statement['effect']);
        $this->assertEquals(['delete', 'admin:*'], $statement['action']);
        $this->assertEquals('*', $statement['resource']);
    }

    /** @test */
    public function it_builds_statement_fluently()
    {
        $statement = PolicyStatement::builder()
            ->allow()
            ->action('read')
            ->action(['write', 'update'])
            ->resource('posts:*')
            ->resource('comments:*')
            ->condition('ip', '192.168.1.0/24')
            ->condition('time', ['after' => '09:00', 'before' => '18:00'])
            ->principal('role:editor')
            ->build();

        $this->assertEquals('allow', $statement['effect']);
        $this->assertEquals(['read', 'write', 'update'], $statement['action']);
        $this->assertEquals(['posts:*', 'comments:*'], $statement['resource']);
        $this->assertEquals([
            'ip' => '192.168.1.0/24',
            'time' => ['after' => '09:00', 'before' => '18:00']
        ], $statement['condition']);
        $this->assertEquals('role:editor', $statement['principal']);
    }

    /** @test */
    public function builder_handles_single_values()
    {
        $statement = PolicyStatement::builder()
            ->deny()
            ->notAction('delete')
            ->resource('important:*')
            ->build();

        $this->assertEquals('deny', $statement['effect']);
        $this->assertEquals('delete', $statement['not_action']);
        $this->assertEquals('important:*', $statement['resource']);
        $this->assertArrayNotHasKey('action', $statement);
    }

    /** @test */
    public function empty_arrays_match_all()
    {
        // 空 action 数组匹配所有操作
        $statement = new PolicyStatement(['resource' => 'posts']);
        $this->assertTrue($statement->matchAction('any-action'));

        // 空 resource 数组匹配所有资源
        $statement = new PolicyStatement(['action' => 'read']);
        $this->assertTrue($statement->matchResource('any-resource'));

        // 空 principal 数组匹配所有主体
        $statement = new PolicyStatement(['action' => 'read', 'resource' => 'posts']);
        $this->assertTrue($statement->matchPrincipal('any-principal'));
    }
}
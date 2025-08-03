<?php

namespace Dybasedev\LunaPrototype\Tests\Unit\Permission;

use Dybasedev\LunaPrototype\Permission\Models\PolicyStatement;
use Dybasedev\LunaPrototype\Permission\Models\PolicyStatementBuilder;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaException;
use Dybasedev\LunaPrototype\Tests\TestCase;

class PolicyStatementTest extends TestCase
{
    /** @test */
    public function 正确解析策略声明()
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
    public function 默认为拒绝效果()
    {
        $statement = new PolicyStatement([]);
        
        $this->assertEquals(PolicyStatement::EFFECT_DENY, $statement->getEffect());
        $this->assertTrue($statement->isDeny());
        $this->assertFalse($statement->isAllow());
    }

    /** @test */
    public function 将单个值包装为数组()
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
    public function 正确匹配动作()
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
    public function 处理排除动作()
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
    public function 使用模式匹配资源()
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
    public function 匹配主体()
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
    public function 验证必填字段()
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
    public function 验证效果值()
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
    public function 允许没有动作的排除动作()
    {
        $statement = new PolicyStatement([
            'effect' => 'allow',
            'not_action' => 'delete',
            'resource' => 'posts',
        ]);

        $this->assertTrue($statement->validate());
    }

    /** @test */
    public function 创建允许声明()
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
    public function 创建拒绝声明()
    {
        $statement = PolicyStatement::deny(['delete', 'admin:*'], '*');

        $this->assertEquals('deny', $statement['effect']);
        $this->assertEquals(['delete', 'admin:*'], $statement['action']);
        $this->assertEquals('*', $statement['resource']);
    }

    /** @test */
    public function 链式构建声明()
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
    public function 构建器处理单值()
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
    public function 空数组匹配所有()
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
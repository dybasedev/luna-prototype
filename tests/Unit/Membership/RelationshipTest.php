<?php

use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandlerConfigure;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;
use Dybasedev\LunaPrototype\Foundation\LunaSessionHolder;
use Dybasedev\LunaPrototype\Membership\LunaMembership;
use Dybasedev\LunaPrototype\Membership\LunaMembershipConfigure;
use Dybasedev\LunaPrototype\Membership\Relationship\Handlers\InvitationRelationshipHandler;
use Dybasedev\LunaPrototype\Membership\Relationship\Handlers\LimitedInvitationRelationshipHandler;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // 创建测试用户表
    Schema::create('test_users', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->timestamps();
    });

    // 创建测试用户模型类
    $userModelClass = new class extends \Illuminate\Database\Eloquent\Model implements SessionHolder {
        use LunaSessionHolder;
        
        protected $table = 'test_users';
        protected $fillable = ['name', 'email'];
        
        public function getOperatorTypeName(): string
        {
            return 'test_user';
        }
        
        public function getSessionHolderContext(): ?array
        {
            return null;
        }
    };
    
    $this->userModel = get_class($userModelClass);

    // 注册处理器
    $handlerConfigure = LunaHandlerConfigure::create()
        ->group('membership-relationships', '会员关系类型', function ($register) {
            $register->handler(InvitationRelationshipHandler::class);
        })
        ->build();

    $this->handler = new LunaHandler(
        $handlerConfigure,
        app('cache.store')
    );

    // 配置会员模块
    $configure = LunaMembershipConfigure::create()
        ->withRelationship()
        ->build();

    // 创建会员管理实例
    $this->membership = new LunaMembership(
        $configure,
        app('cache.store'),
        $this->handler
    );
});

afterEach(function () {
    Schema::dropIfExists('test_users');
});

test('可以创建会员关系', function () {
    // 创建测试用户
    $parent = $this->userModel::create([
        'name' => '邀请人',
        'email' => 'inviter@example.com',
    ]);

    $child = $this->userModel::create([
        'name' => '被邀请人',
        'email' => 'invitee@example.com',
    ]);

    // 建立邀请关系
    $relationship = $this->membership->relationship()->createRelationship(
        'invitation',
        $parent,
        $child,
        ['invitation_code' => 'TEST123']
    );

    expect($relationship)->not->toBeNull();
    expect($relationship->relationship_type)->toBe(hash_code('invitation'));
    expect($relationship->owner_id)->toBe($child->id);
    expect($relationship->owner_type)->toBe($child->getOperatorType());
    expect($relationship->depth)->toBe(1);
});

test('可以查询直接上级', function () {
    // 创建测试用户
    $parent = $this->userModel::create([
        'name' => '邀请人',
        'email' => 'inviter@example.com',
    ]);

    $child = $this->userModel::create([
        'name' => '被邀请人',
        'email' => 'invitee@example.com',
    ]);

    // 建立关系
    $this->membership->relationship()->createRelationship(
        'invitation', 
        $parent, 
        $child
    );

    // 查询上级
    $foundParent = $this->membership->relationship()->getParent(
        'invitation', 
        $child
    );

    expect($foundParent)->not->toBeNull();
    expect($foundParent->id)->toBe($parent->id);
});

test('可以查询直接下级', function () {
    // 创建测试用户
    $parent = $this->userModel::create([
        'name' => '邀请人',
        'email' => 'inviter@example.com',
    ]);

    $child1 = $this->userModel::create([
        'name' => '被邀请人1',
        'email' => 'invitee1@example.com',
    ]);

    $child2 = $this->userModel::create([
        'name' => '被邀请人2',
        'email' => 'invitee2@example.com',
    ]);

    // 建立关系
    $this->membership->relationship()->createRelationship(
        'invitation', 
        $parent, 
        $child1
    );
    $this->membership->relationship()->createRelationship(
        'invitation', 
        $parent, 
        $child2
    );

    // 查询下级
    $children = $this->membership->relationship()->getChildren(
        'invitation', 
        $parent
    );

    expect($children)->toHaveCount(2);
    $childIds = $children->map(fn($holder) => $holder->id)->toArray();
    expect($childIds)->toContain($child1->id, $child2->id);
});

test('可以查询多级关系', function () {
    // 创建三级用户
    $level1 = $this->userModel::create([
        'name' => '一级',
        'email' => 'level1@example.com',
    ]);

    $level2 = $this->userModel::create([
        'name' => '二级',
        'email' => 'level2@example.com',
    ]);

    $level3 = $this->userModel::create([
        'name' => '三级',
        'email' => 'level3@example.com',
    ]);

    // 建立关系链
    $this->membership->relationship()->createRelationship(
        'invitation', 
        $level1, 
        $level2
    );
    $this->membership->relationship()->createRelationship(
        'invitation', 
        $level2, 
        $level3
    );

    // 查询所有上级
    $ancestors = $this->membership->relationship()->getAncestors(
        'invitation', 
        $level3
    );
    expect($ancestors)->toHaveCount(2);
    $ancestorIds = $ancestors->map(fn($holder) => $holder->id)->toArray();
    expect($ancestorIds)->toBe([$level1->id, $level2->id]);

    // 查询所有下级
    $descendants = $this->membership->relationship()->getDescendants(
        'invitation', 
        $level1
    );
    expect($descendants)->toHaveCount(2);
    $descendantIds = $descendants->map(fn($holder) => $holder->id)->toArray();
    expect($descendantIds)->toContain($level2->id, $level3->id);
});

test('不允许修改已建立的邀请关系', function () {
    // 创建测试用户
    $parent1 = $this->userModel::create([
        'name' => '邀请人1',
        'email' => 'inviter1@example.com',
    ]);

    $parent2 = $this->userModel::create([
        'name' => '邀请人2',
        'email' => 'inviter2@example.com',
    ]);

    $child = $this->userModel::create([
        'name' => '被邀请人',
        'email' => 'invitee@example.com',
    ]);

    // 建立第一个关系
    $this->membership->relationship()->createRelationship(
        'invitation', 
        $parent1, 
        $child
    );

    // 尝试建立第二个关系应该失败
    expect(function () use ($parent2, $child) {
        $this->membership->relationship()->createRelationship(
            'invitation', 
            $parent2, 
            $child
        );
    })->toThrow(\Dybasedev\LunaPrototype\Foundation\Exception\LunaException::class);
});

test('检查最大深度限制', function () {
    // 重新注册处理器组，包含新的关系类型
    $handlerConfigure = LunaHandlerConfigure::create()
        ->group('membership-relationships', '会员关系类型', function ($register) {
            $register->handler(InvitationRelationshipHandler::class);
            $register->handler(LimitedInvitationRelationshipHandler::class);
        })
        ->build();

    $this->handler = new LunaHandler(
        $handlerConfigure,
        app('cache.store')
    );

    // 重新创建会员管理实例
    $configure = LunaMembershipConfigure::create()
        ->withRelationship()
        ->build();

    $this->membership = new LunaMembership(
        $configure,
        app('cache.store'),
        $this->handler
    );

    // 创建用户
    $level1 = $this->userModel::create(['name' => '一级', 'email' => 'l1@example.com']);
    $level2 = $this->userModel::create(['name' => '二级', 'email' => 'l2@example.com']);
    $level3 = $this->userModel::create(['name' => '三级', 'email' => 'l3@example.com']);

    // 建立两级关系
    $this->membership->relationship()->createRelationship(
        'limited-invitation', 
        $level1, 
        $level2
    );

    // 尝试建立第三级应该失败
    expect(function () use ($level2, $level3) {
        $this->membership->relationship()->createRelationship(
            'limited-invitation', 
            $level2, 
            $level3
        );
    })->toThrow(\Dybasedev\LunaPrototype\Foundation\Exception\LunaException::class);
});
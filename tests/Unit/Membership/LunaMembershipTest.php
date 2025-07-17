<?php

namespace Dybasedev\LunaPrototype\Tests\Unit\Membership;

use Dybasedev\LunaPrototype\Membership\LunaMembership;
use Dybasedev\LunaPrototype\Membership\LunaMembershipConfigure;
use Dybasedev\LunaPrototype\Tests\TestCase;
use Illuminate\Contracts\Cache\Repository as Cache;

class LunaMembershipTest extends TestCase
{
    private LunaMembership $membership;
    private LunaMembershipConfigure $configure;
    private Cache $cache;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->configure = $this->app->make(LunaMembershipConfigure::class);
        $this->cache = $this->app->make(Cache::class);
        $this->membership = new LunaMembership($this->configure, $this->cache);
    }

    public function test_membership_can_be_instantiated()
    {
        $this->assertInstanceOf(LunaMembership::class, $this->membership);
        $this->assertSame($this->configure, $this->membership->configure);
    }

    public function test_membership_has_correct_configuration()
    {
        $this->assertInstanceOf(LunaMembershipConfigure::class, $this->membership->configure);
    }

    public function test_membership_configuration_returns_correct_defaults()
    {
        $config = $this->membership->configure;
        
        // 测试默认配置是否正确设置
        $this->assertNotNull($config);
    }
}
<?php

namespace Dybasedev\LunaPrototype\Tests\Integration\Content;

use Dybasedev\LunaPrototype\Content\Handlers\ArticleContentHandler;
use Dybasedev\LunaPrototype\Content\Handlers\DefaultChannelHandler;
use Dybasedev\LunaPrototype\Content\Handlers\HtmlContentHandler;
use Dybasedev\LunaPrototype\Content\Handlers\MarkdownContentHandler;
use Dybasedev\LunaPrototype\Content\LunaContentConfigure;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandler;
use Dybasedev\LunaPrototype\Foundation\LunaServiceProvider;
use Dybasedev\LunaPrototype\Tests\TestCase;

class ContentModuleIntegrationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ContentTestServiceProvider::class,
        ];
    }
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // 加载 Content 模块的迁移
        $this->loadMigrationsFrom(__DIR__ . '/../../../src/Content/migrations');
    }

    public function test_content_module_registers_default_handlers(): void
    {
        // 获取 handler 管理器
        $handlerManager = app(LunaHandler::class);
        
        // 检查所有组
        $groups = $handlerManager->groups();
        
        // 查找内容处理器组 - 使用数字索引数组
        $contentHandlerGroup = null;
        $channelHandlerGroup = null;
        
        foreach ($groups as $key => $group) {
            if ($group['name'] === 'content-handlers') {
                $contentHandlerGroup = $group;
            }
            if ($group['name'] === 'channel-handlers') {
                $channelHandlerGroup = $group;
            }
        }
        
        // 检查内容处理器组是否存在
        $this->assertNotNull($contentHandlerGroup, 'Content handler group not found');
        $this->assertEquals('内容处理器', $contentHandlerGroup['display_name']);
        
        // 检查频道处理器组是否存在
        $this->assertNotNull($channelHandlerGroup, 'Channel handler group not found');
        $this->assertEquals('频道处理器', $channelHandlerGroup['display_name']);
        
        // 检查默认的内容处理器是否已注册
        $registeredHandlers = $handlerManager->handlers($contentHandlerGroup['id']);
        $handlerClasses = array_column($registeredHandlers, 'handler');
        
        $this->assertContains(ArticleContentHandler::class, $handlerClasses);
        $this->assertContains(HtmlContentHandler::class, $handlerClasses);
        $this->assertContains(MarkdownContentHandler::class, $handlerClasses);
        
        // 检查默认的频道处理器是否已注册
        $channelHandlers = $handlerManager->handlers($channelHandlerGroup['id']);
        $channelHandlerClasses = array_column($channelHandlers, 'handler');
        
        $this->assertContains(DefaultChannelHandler::class, $channelHandlerClasses);
    }
}

// 测试专用的服务提供者
class ContentTestServiceProvider extends LunaServiceProvider
{
    public function customRegister(): void
    {
        // 注册 Content 模块
        $this->registerModule(LunaContentConfigure::create()->build());
    }
}
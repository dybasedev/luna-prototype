<?php

namespace Dybasedev\LunaPrototype\Tests\Unit\Foundation\Commands;

use Dybasedev\LunaPrototype\Tests\TestCase;
use Dybasedev\LunaPrototype\Foundation\LunaServiceProvider;
use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccountConfigure;
use Dybasedev\LunaPrototype\Trade\LunaTradeConfigure;
use Illuminate\Support\Facades\File;

class AppPublishModelsCommandTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            TestServiceProvider::class,
        ];
    }
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // 清理测试目录
        File::deleteDirectory(app_path('Models'));
    }
    
    protected function tearDown(): void
    {
        // 清理测试生成的文件
        File::deleteDirectory(app_path('Models'));
        
        parent::tearDown();
    }
    
    /**
     * 测试发布模型命令可以列出可用模块
     */
    public function test_publish_models_command_lists_available_modules()
    {
        $this->artisan('app:publish-models', ['--dry-run' => true])
            ->expectsOutput('=> 发布 Luna 模块模型到应用程序')
            ->expectsOutput('预览模式：不会创建任何文件')
            ->expectsOutputToContain('处理模块: luna.assets-account')
            ->expectsOutputToContain('处理模块: luna.trade')
            ->assertSuccessful();
    }
    
    /**
     * 测试发布模型命令可以创建模型文件
     */
    public function test_publish_models_command_creates_model_files()
    {
        $this->artisan('app:publish-models', ['--module' => ['luna.assets-account']])
            ->expectsOutput('=> 发布 Luna 模块模型到应用程序')
            ->expectsOutputToContain('处理模块: luna.assets-account')
            ->assertSuccessful();
            
        // 检查文件是否创建
        $this->assertFileExists(app_path('Models/AssetsAccount.php'));
        $this->assertFileExists(app_path('Models/AssetsAccountChangeLog.php'));
        $this->assertFileExists(app_path('Models/AssetsAccountType.php'));
        
        // 检查文件内容
        $content = File::get(app_path('Models/AssetsAccount.php'));
        
        // 验证继承关系
        $this->assertStringContainsString('use Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccount as BaseAssetsAccount;', $content);
        $this->assertStringContainsString('class AssetsAccount extends BaseAssetsAccount', $content);
        
        // 验证文档注释
        $this->assertStringContainsString('继承自 Luna 模块的 AssetsAccount 模型', $content);
    }
    
    /**
     * 测试发布模型命令处理无效模块
     */
    public function test_publish_models_command_with_invalid_module()
    {
        $this->artisan('app:publish-models', ['--module' => ['luna.invalid']])
            ->expectsOutput('=> 发布 Luna 模块模型到应用程序')
            ->expectsOutput('没有找到指定的模块')
            ->assertExitCode(1);
    }
}

// 测试专用的服务提供者
class TestServiceProvider extends LunaServiceProvider
{
    public function customRegister(): void
    {
        // 注册一些测试模块
        $this->registerModule(LunaAssetsAccountConfigure::create()->build());
        $this->registerModule(LunaTradeConfigure::create()->build());
    }
}
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
    
    /**
     * 测试发布模型命令处理已存在的文件 - 跳过选项
     */
    public function test_publish_models_command_skips_existing_files()
    {
        // 先创建一个已存在的文件
        File::makeDirectory(app_path('Models'), 0755, true);
        File::put(app_path('Models/AssetsAccount.php'), '<?php // Existing file');
        
        $this->artisan('app:publish-models', [
                '--module' => ['luna.assets-account'],
                '--no-interaction' => true
            ])
            ->expectsOutput('=> 发布 Luna 模块模型到应用程序')
            ->expectsOutputToContain('跳过模型: AssetsAccount')
            ->assertSuccessful();
            
        // 验证文件内容没有变化
        $content = File::get(app_path('Models/AssetsAccount.php'));
        $this->assertEquals('<?php // Existing file', $content);
    }
    
    /**
     * 测试发布模型命令处理已存在的文件 - 添加前缀选项
     */
    public function test_publish_models_command_adds_prefix_to_conflicting_models()
    {
        // 先创建一个已存在的文件
        File::makeDirectory(app_path('Models'), 0755, true);
        File::put(app_path('Models/AssetsAccount.php'), '<?php // Existing file');
        
        $this->artisan('app:publish-models', [
                '--module' => ['luna.assets-account'],
                '--prefix' => true
            ])
            ->expectsOutput('=> 发布 Luna 模块模型到应用程序')
            ->expectsOutputToContain('使用前缀名称: LunaAssetsAccount')
            ->assertSuccessful();
            
        // 验证原文件没有变化
        $originalContent = File::get(app_path('Models/AssetsAccount.php'));
        $this->assertEquals('<?php // Existing file', $originalContent);
        
        // 验证新文件创建成功
        $this->assertFileExists(app_path('Models/LunaAssetsAccount.php'));
        $newContent = File::get(app_path('Models/LunaAssetsAccount.php'));
        
        // 验证继承关系正确
        $this->assertStringContainsString('use Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccount;', $newContent);
        $this->assertStringContainsString('class LunaAssetsAccount extends AssetsAccount', $newContent);
        $this->assertStringContainsString('继承自 Luna 模块的 AssetsAccount 模型', $newContent);
    }
    
    /**
     * 测试发布模型命令处理已存在的文件 - 强制覆盖选项
     */
    public function test_publish_models_command_overwrites_with_force_option()
    {
        // 先创建一个已存在的文件
        File::makeDirectory(app_path('Models'), 0755, true);
        File::put(app_path('Models/AssetsAccount.php'), '<?php // Existing file');
        
        $this->artisan('app:publish-models', [
                '--module' => ['luna.assets-account'],
                '--force' => true
            ])
            ->expectsOutput('=> 发布 Luna 模块模型到应用程序')
            ->expectsOutputToContain('已创建: ' . app_path('Models/AssetsAccount.php'))
            ->assertSuccessful();
            
        // 验证文件被覆盖
        $content = File::get(app_path('Models/AssetsAccount.php'));
        $this->assertStringContainsString('class AssetsAccount extends BaseAssetsAccount', $content);
        $this->assertStringNotContainsString('// Existing file', $content);
    }
    
    /**
     * 测试发布模型命令的交互式选择功能
     */
    public function test_publish_models_command_interactive_choice_for_conflicts()
    {
        // 先创建一个已存在的文件
        File::makeDirectory(app_path('Models'), 0755, true);
        File::put(app_path('Models/AssetsAccount.php'), '<?php // Existing file');
        
        $this->artisan('app:publish-models', ['--module' => ['luna.assets-account']])
            ->expectsOutput('=> 发布 Luna 模块模型到应用程序')
            ->expectsOutputToContain('模型 AssetsAccount 已存在！')
            ->expectsChoice('    请选择处理方式:', 'prefix', [
                'skip' => '跳过此模型',
                'prefix' => '添加 Luna 前缀（创建 LunaAssetsAccount）',
                'overwrite' => '覆盖现有文件'
            ])
            ->expectsOutputToContain('使用前缀名称: LunaAssetsAccount')
            ->assertSuccessful();
            
        // 验证创建了带前缀的文件
        $this->assertFileExists(app_path('Models/LunaAssetsAccount.php'));
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
<?php

use Dybasedev\LunaPrototype\Foundation\LunaServiceProvider;
use Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccountConfigure;
use Dybasedev\LunaPrototype\Trade\LunaTradeConfigure;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // 清理测试目录
    File::deleteDirectory(app_path('Models'));
    
    // 注册测试服务提供者
    app()->register(TestServiceProvider::class);
});

afterEach(function () {
    // 清理测试生成的文件
    File::deleteDirectory(app_path('Models'));
});

test('发布模型命令可以列出可用模块', function () {
    $this->artisan('app:publish-models', ['--dry-run' => true])
        ->expectsOutput('=> 发布 Luna 模块模型到应用程序')
        ->expectsOutput('预览模式：不会创建任何文件')
        ->expectsOutputToContain('处理模块: luna.assets-account')
        ->expectsOutputToContain('处理模块: luna.trade')
        ->assertSuccessful();
});

test('发布模型命令可以创建模型文件', function () {
    $this->artisan('app:publish-models', ['--module' => ['luna.assets-account']])
        ->expectsOutput('=> 发布 Luna 模块模型到应用程序')
        ->expectsOutputToContain('处理模块: luna.assets-account')
        ->assertSuccessful();
        
    // 检查文件是否创建
    expect(app_path('Models/AssetsAccount.php'))->toBeFile();
    expect(app_path('Models/AssetsAccountChangeLog.php'))->toBeFile();
    expect(app_path('Models/AssetsAccountType.php'))->toBeFile();
    
    // 检查文件内容
    $content = File::get(app_path('Models/AssetsAccount.php'));
    
    // 验证继承关系
    expect($content)->toContain('use Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccount as BaseAssetsAccount;');
    expect($content)->toContain('class AssetsAccount extends BaseAssetsAccount');
    
    // 验证文档注释
    expect($content)->toContain('继承自 Luna 模块的 AssetsAccount 模型');
});

test('发布模型命令处理无效模块', function () {
    $this->artisan('app:publish-models', ['--module' => ['luna.invalid']])
        ->expectsOutput('=> 发布 Luna 模块模型到应用程序')
        ->expectsOutput('没有找到指定的模块')
        ->assertExitCode(1);
});

test('发布模型命令跳过已存在的文件', function () {
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
    expect($content)->toBe('<?php // Existing file');
});

test('发布模型命令为冲突模型添加前缀', function () {
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
    expect($originalContent)->toBe('<?php // Existing file');
    
    // 验证新文件创建成功
    expect(app_path('Models/LunaAssetsAccount.php'))->toBeFile();
    $newContent = File::get(app_path('Models/LunaAssetsAccount.php'));
    
    // 验证继承关系正确
    expect($newContent)->toContain('use Dybasedev\LunaPrototype\AssetsAccount\Models\AssetsAccount;');
    expect($newContent)->toContain('class LunaAssetsAccount extends AssetsAccount');
    expect($newContent)->toContain('继承自 Luna 模块的 AssetsAccount 模型');
});

test('发布模型命令强制覆盖已存在的文件', function () {
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
    expect($content)->toContain('class AssetsAccount extends BaseAssetsAccount');
    expect($content)->not->toContain('// Existing file');
});

test('发布模型命令的交互式选择功能', function () {
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
    expect(app_path('Models/LunaAssetsAccount.php'))->toBeFile();
});

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
#!/usr/bin/env php
<?php

/**
 * Luna Prototype 测试环境配置生成脚本
 * 
 * 用于快速生成 .env.testing 文件，便于贡献者和 CI 环境快速搭建测试环境
 */

class TestingEnvSetup
{
    private string $envPath;
    private array $config = [];

    public function __construct()
    {
        $this->envPath = dirname(__DIR__) . '/.env.testing';
    }

    public function run(): void
    {
        $this->printHeader();
        
        if ($this->checkExistingFile()) {
            return;
        }

        $this->collectConfiguration();
        $this->generateEnvFile();
        $this->printSuccess();
    }

    private function printHeader(): void
    {
        echo "\n";
        echo "═══════════════════════════════════════════════════════════════\n";
        echo "  Luna Prototype 测试环境配置生成器\n";
        echo "═══════════════════════════════════════════════════════════════\n";
        echo "\n";
        echo "此脚本将帮助您生成 .env.testing 文件用于运行测试。\n";
        echo "\n";
    }

    private function checkExistingFile(): bool
    {
        if (file_exists($this->envPath)) {
            echo "⚠️  发现已存在的 .env.testing 文件。\n";
            $overwrite = $this->askYesNo("是否要覆盖现有文件？", false);
            
            if (!$overwrite) {
                echo "\n✅ 操作已取消。\n";
                return true;
            }
            
            echo "\n";
        }
        
        return false;
    }

    private function collectConfiguration(): void
    {
        echo "📋 请配置测试环境参数（直接回车使用默认值）：\n\n";

        // 数据库配置
        echo "🗄️  数据库配置：\n";
        $this->config['DB_TEST_HOST'] = $this->ask("数据库主机", "127.0.0.1");
        $this->config['DB_TEST_PORT'] = $this->ask("数据库端口", "3306");
        $this->config['DB_TEST_DATABASE'] = $this->ask("测试数据库名", "luna_prototype_test");
        $this->config['DB_TEST_USERNAME'] = $this->ask("数据库用户名", "root");
        $this->config['DB_TEST_PASSWORD'] = $this->ask("数据库密码", "root");

        echo "\n";

        // 确认配置
        echo "📝 配置确认：\n";
        echo "   数据库: {$this->config['DB_TEST_USERNAME']}@{$this->config['DB_TEST_HOST']}:{$this->config['DB_TEST_PORT']}/{$this->config['DB_TEST_DATABASE']}\n";
        
        $confirm = $this->askYesNo("\n确认生成配置文件？", true);
        
        if (!$confirm) {
            echo "\n❌ 操作已取消。\n";
            exit(1);
        }

        echo "\n";
    }

    private function generateEnvFile(): void
    {
        echo "🔧 正在生成 .env.testing 文件...\n";

        $appKey = $this->generateAppKey();

        $content = $this->buildEnvContent($appKey);

        if (file_put_contents($this->envPath, $content) === false) {
            echo "\n❌ 生成 .env.testing 文件失败！\n";
            exit(1);
        }
    }

    private function generateAppKey(): string
    {
        echo "🔑 生成应用密钥...\n";
        return 'base64:' . base64_encode(random_bytes(32));
    }

    private function buildEnvContent(string $appKey): string
    {
        return <<<ENV
# Luna Prototype 测试环境配置
# 此文件由 scripts/setup-testing-env.php 自动生成

APP_ENV=testing
APP_KEY={$appKey}
APP_DEBUG=true

# MySQL测试数据库配置
DB_TEST_HOST={$this->config['DB_TEST_HOST']}
DB_TEST_PORT={$this->config['DB_TEST_PORT']}
DB_TEST_DATABASE={$this->config['DB_TEST_DATABASE']}
DB_TEST_USERNAME={$this->config['DB_TEST_USERNAME']}
DB_TEST_PASSWORD={$this->config['DB_TEST_PASSWORD']}

# 缓存配置
CACHE_STORE=array

# 测试优化配置
BCRYPT_ROUNDS=4
MAIL_MAILER=array
QUEUE_CONNECTION=sync
SESSION_DRIVER=array

# 日志配置
LOG_CHANNEL=single
LOG_LEVEL=debug

ENV;
    }

    private function printSuccess(): void
    {
        echo "✅ .env.testing 文件生成成功！\n";
        echo "\n";
        echo "🚀 接下来您可以：\n";
        echo "   1. 确保数据库 '{$this->config['DB_TEST_DATABASE']}' 存在\n";
        echo "   2. 运行测试: ./vendor/bin/pest\n";
        echo "   3. 运行迁移: php artisan migrate --env=testing\n";
        echo "\n";
        echo "📖 更多信息请查看项目 README.md\n";
        echo "\n";
    }

    private function ask(string $question, string $default = ''): string
    {
        $prompt = "   {$question}";
        if ($default) {
            $prompt .= " [默认: {$default}]";
        }
        $prompt .= ": ";

        echo $prompt;
        $input = trim(fgets(STDIN));

        return $input ?: $default;
    }

    private function askYesNo(string $question, bool $default = true): bool
    {
        $defaultText = $default ? 'Y/n' : 'y/N';
        echo "{$question} [{$defaultText}]: ";
        
        $input = trim(strtolower(fgets(STDIN)));
        
        if ($input === '') {
            return $default;
        }
        
        return in_array($input, ['y', 'yes', '是']);
    }
}

// 检查是否在 CLI 环境中运行
if (php_sapi_name() !== 'cli') {
    echo "此脚本只能在命令行中运行。\n";
    exit(1);
}

// 检查是否在正确的目录中运行
$composerFile = dirname(__DIR__) . '/composer.json';
if (!file_exists($composerFile)) {
    echo "❌ 请在 Luna Prototype 项目根目录中运行此脚本。\n";
    exit(1);
}

// 运行设置
try {
    (new TestingEnvSetup())->run();
} catch (Exception $e) {
    echo "\n❌ 发生错误: " . $e->getMessage() . "\n";
    exit(1);
}
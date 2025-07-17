#!/usr/bin/env php
<?php

/**
 * Luna Prototype CI 测试环境配置生成脚本
 * 
 * 用于 CI 环境快速生成 .env.testing 文件（非交互式）
 * 
 * Usage:
 *   php scripts/setup-testing-env-ci.php
 *   php scripts/setup-testing-env-ci.php --db-host=localhost --db-user=root --db-pass=secret
 */

class CITestingEnvSetup
{
    private array $options = [
        'db-host' => '127.0.0.1',
        'db-port' => '3306',
        'db-database' => 'luna_prototype_test',
        'db-username' => 'root',
        'db-password' => 'root',
        'force' => false,
    ];

    private string $envPath;

    public function __construct()
    {
        $this->envPath = dirname(__DIR__) . '/.env.testing';
        $this->parseArguments();
    }

    public function run(): void
    {
        if ($this->checkExistingFile()) {
            return;
        }

        $this->generateEnvFile();
        $this->printSuccess();
    }

    private function parseArguments(): void
    {
        $args = $_SERVER['argv'] ?? [];
        
        foreach ($args as $arg) {
            if (strpos($arg, '--') === 0) {
                $parts = explode('=', substr($arg, 2), 2);
                $key = $parts[0];
                $value = $parts[1] ?? true;
                
                if (array_key_exists($key, $this->options)) {
                    $this->options[$key] = $value;
                }
            }
        }
    }

    private function checkExistingFile(): bool
    {
        if (file_exists($this->envPath) && !$this->options['force']) {
            echo "❌ .env.testing 已存在。使用 --force 参数强制覆盖。\n";
            return true;
        }
        
        return false;
    }

    private function generateEnvFile(): void
    {
        echo "🔧 生成 CI 测试环境配置...\n";

        $appKey = 'base64:' . base64_encode(random_bytes(32));
        $content = $this->buildEnvContent($appKey);

        if (file_put_contents($this->envPath, $content) === false) {
            echo "❌ 生成 .env.testing 文件失败！\n";
            exit(1);
        }
    }

    private function buildEnvContent(string $appKey): string
    {
        return <<<ENV
# Luna Prototype CI 测试环境配置
# 此文件由 scripts/setup-testing-env-ci.php 自动生成

APP_ENV=testing
APP_KEY={$appKey}
APP_DEBUG=true

# MySQL测试数据库配置
DB_TEST_HOST={$this->options['db-host']}
DB_TEST_PORT={$this->options['db-port']}
DB_TEST_DATABASE={$this->options['db-database']}
DB_TEST_USERNAME={$this->options['db-username']}
DB_TEST_PASSWORD={$this->options['db-password']}

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
        echo "✅ CI 测试环境配置生成成功！\n";
        echo "   数据库: {$this->options['db-username']}@{$this->options['db-host']}:{$this->options['db-port']}/{$this->options['db-database']}\n";
    }

    public static function printHelp(): void
    {
        echo <<<HELP
Luna Prototype CI 测试环境配置生成器

用法:
  php scripts/setup-testing-env-ci.php [选项]

选项:
  --db-host=HOST        数据库主机 (默认: 127.0.0.1)
  --db-port=PORT        数据库端口 (默认: 3306)
  --db-database=NAME    数据库名称 (默认: luna_prototype_test)
  --db-username=USER    数据库用户名 (默认: root)
  --db-password=PASS    数据库密码 (默认: root)
  --force               强制覆盖现有文件
  --help                显示此帮助信息

示例:
  # 使用默认配置
  php scripts/setup-testing-env-ci.php

  # 自定义数据库配置
  php scripts/setup-testing-env-ci.php --db-host=localhost --db-user=test --db-pass=secret

  # 强制覆盖现有文件
  php scripts/setup-testing-env-ci.php --force

HELP;
    }
}

// 检查帮助参数
if (in_array('--help', $_SERVER['argv'] ?? [])) {
    CITestingEnvSetup::printHelp();
    exit(0);
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
    (new CITestingEnvSetup())->run();
} catch (Exception $e) {
    echo "❌ 发生错误: " . $e->getMessage() . "\n";
    exit(1);
}
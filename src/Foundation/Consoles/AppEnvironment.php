<?php

namespace Dybasedev\LunaPrototype\Foundation\Consoles;


use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class AppEnvironment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:env {name=default : 需要切换的环境名称，会自动切换到指定 Environment 文件，若不存在会自动创建}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '切换本地 App 环境文件，该命令不要用于线上环境';

    protected array $keyDescription = [
        'app_name' => '应用名称',
        'app_env' => '环境名称',
        'app_debug' => '是否开启调试模式',
        'app_timezone' => '时区',
        'app_locale' => '默认语言',
        'db_connection' => '数据库连接',
        'db_host' => '数据库地址',
        'db_port' => '数据库端口',
        'db_database' => '数据库名称',
        'db_username' => '数据库用户名',
        'db_password' => '数据库密码',
        'redis_client' => 'Redis 客户端',
        'redis_host' => 'Redis 地址',
        'redis_port' => 'Redis 端口',
        'redis_password' => 'Redis 密码',
    ];

    protected array $defaultEnv = [
        'app_name' => 'LunaPrototype',
        'app_env' => 'local',
        'app_debug' => 'true',
        'app_timezone' => 'UTC',
        'app_locale' => 'zh-cn',
        'app_fallback_locale' => 'en',
        'app_faker_locale' => 'en_US',
        'app_maintenance_driver' => 'file',
        'php_cli_server_workers' => '4',
        'db_connection' => 'mysql',
        'db_host' => '127.0.0.1',
        'db_port' => '3306',
        'db_database' => 'luna_prototype_api',
        'db_username' => 'root',
        'db_password' => 'root',
        'session_driver' => 'redis',
        'session_lifetime' => '120',
        'session_encrypt' => 'false',
        'session_path' => '/',
        'session_domain' => 'null',
        'cache_store' => 'redis',
        'cache_prefix' => '',
        'redis_client' => 'phpredis',
        'redis_host' => '127.0.0.1',
        'redis_password' => 'null',
        'redis_port' => '6379',
        'broadcast_connection' => 'log',
        'filesystem_disk' => 'local',
        'queue_connection' => 'redis',
        'mail_mailer' => 'log',
        'mail_scheme' => 'null',
        'mail_host' => '127.0.0.1',
        'mail_port' => '2525',
        'mail_username' => 'null',
        'mail_password' => 'null',
        'mail_from_address' => 'hello@example.com',
        'mail_from_name' => '${APP_NAME}',
        'hash_driver' => 'argon2id',
        'jwt_secret' => '',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $environmentName = $this->argument('name');
        $exists = true;

        if (file_exists(base_path(".env.project.{$environmentName}"))) {
            $this->info("切换到环境文件 .env.project.{$environmentName}");
        } else {
            $this->error("环境文件 .env.project.{$environmentName} 不存在");
            $exists = false;

            if ($this->confirm('是否创建该环境文件？')) {
                $env = [];

                foreach ($this->keyDescription as $key => $description) {
                    $env[$key] = $this->ask($description, $this->defaultEnv[$key]);
                }

                file_put_contents(base_path(".env.project.{$environmentName}"),
                    $this->buildEnvironmentFileContent($env));

                $this->info("已经创建环境文件 .env.project.{$environmentName}");
            } else {
                return;
            }
        }

        // 通过执行软链接命令切换 .env
        Process::path(base_path())->run(['ln', '-sf', ".env.project.{$environmentName}", '.env']);

        $this->info('建立环境软链接成功!');

        if (!$exists) {
            $this->info('新建的环境上下文，请手动执行后续初始化操作');
        }
    }

    private function buildEnvironmentFileContent(array $env): false|string
    {
        ob_start();
        include __DIR__ . '/Stubs/env-template.php';
        return ob_get_clean();
    }
}

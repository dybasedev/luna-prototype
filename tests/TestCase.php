<?php

namespace Dybasedev\LunaPrototype\Tests;

use Dybasedev\LunaPrototype\Foundation\LunaServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // 手动加载 .env.testing
        if (file_exists(__DIR__ . '/../.env.testing')) {
            $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..', '.env.testing');
            $dotenv->load();
        }
        
        parent::setUp();
        
        $this->loadMigrationsFrom(__DIR__ . '/../src/Foundation/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../src/AssetsAccount/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../src/Schedule/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../src/Membership/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../src/UnitConversion/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../src/HoldingObject/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../src/Trade/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../src/Permission/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../src/DnW/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../src/Content/migrations');
        
        $this->artisan('migrate', ['--database' => 'testing'])->run();
        
        // 注册 AssetsAccount 服务
        $this->app->bind('luna.assets-account', function () {
            return $this->app->make(\Dybasedev\LunaPrototype\AssetsAccount\LunaAssetsAccount::class);
        });
    }

    protected function getPackageProviders($app): array
    {
        return [
            LunaServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // 设置测试数据库 - 使用MySQL
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'mysql',
            'host' => env('DB_TEST_HOST', '127.0.0.1'),
            'port' => env('DB_TEST_PORT', '3306'),
            'database' => env('DB_TEST_DATABASE', 'luna_prototype_test'),
            'username' => env('DB_TEST_USERNAME', 'root'),
            'password' => env('DB_TEST_PASSWORD', ''),
            'unix_socket' => env('DB_TEST_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                \PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ]);
        
        // 设置缓存驱动 - 从环境变量读取
        $app['config']->set('cache.default', env('CACHE_STORE', 'array'));
    }
}

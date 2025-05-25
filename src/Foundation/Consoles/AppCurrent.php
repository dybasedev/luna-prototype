<?php

namespace Dybasedev\LunaPrototype\Foundation\Consoles;


use Illuminate\Console\Command;

class AppCurrent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:current';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '展示当前环境文件信息';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->laravel->basePath('.env');

        if (file_exists($this->laravel->basePath('.env'))) {
            $this->info("当前环境文件: {$this->laravel->basePath('.env')}");
            $this->newLine();

            $data = [
                'app' => [
                    'App Name' => config('app.name'),
                    'App URL' => config('app.url'),
                    'App Debug' => config('app.debug') ? 'true' : 'false',
                    'App Locale' => config('app.locale'),
                    'App Fallback Locale' => config('app.fallback_locale'),
                    'App Timezone' => config('app.timezone'),
                    'App Key' => config('app.key'),
                ],
                'database' => [
                    'Database Default Connection' => config(sprintf('database.connections.%s.host', config('database.default'))),
                    'Database Default Name' => config(sprintf('database.connections.%s.database', config('database.default'))),
                    'Database Default Username' => config(sprintf('database.connections.%s.username', config('database.default'))),
                    'Database Default Password' => config(sprintf('database.connections.%s.password', config('database.default'))),
                ],
                'redis' => [
                    'Redis Default Host' => config('database.redis.default.host'),
                    'Redis Default Port' => config('database.redis.default.port'),
                    'Redis Default Password' => config('database.redis.default.password'),
                ],
            ];

            foreach ($data as $key => $value) {
                $this->table([
                    'Key',
                    'Value',
                ], array_map(function ($value, $key) {
                    return [
                        $key,
                        $value,
                    ];
                }, $value, array_keys($value)));

                $this->newLine();
            }

            $this->info("当前环境文件: {$this->laravel->basePath('.env')}");
        } else {
            $this->error('未找到当前环境文件');
        }
    }
}

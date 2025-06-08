<?php

namespace Dybasedev\LunaPrototype\Foundation\Consoles;

use Dybasedev\LunaPrototype\Foundation\Installation;
use Dybasedev\LunaPrototype\Foundation\LunaApplicationConfigure;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

class AppInstall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install application';

    /**
     * Execute the console command.
     * @throws Throwable
     */
    public function handle(): void
    {
        $this->info(sprintf('=> Initialize application [%s]', config('app.name')));

        // 初始化
        $this->comment('Execute key:generate');
        $this->call('key:generate', ['--no-interaction' => true]);

        $this->comment('Execute migrate');
        $this->call('migrate');

        // 安装
        try {
            DB::transaction(function () {
                Model::unguarded(function () {
                    $configure = $this->laravel->make(LunaApplicationConfigure::class);

                    foreach ($configure->installations as $installation) {
                        /** @var Installation $instance */
                        $instance = $this->laravel->make($installation);
                        $instance->withOutput($this->output)->install();
                    }
                });
            });
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage());
            $this->error($throwable->getTraceAsString());

            $this->error('=> Installation failed.');
            return;
        }

        // 清理缓存
        $this->laravel->make('cache')->clear();
    }
}

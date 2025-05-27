<?php

namespace Dybasedev\LunaPrototype\Foundation;

use Closure;
use Dybasedev\LunaPrototype\Foundation\BusinessEvent\LunaBusinessConfigure;
use Dybasedev\LunaPrototype\Foundation\Configuration\LunaConfigurationConfigure;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaExceptionConfigure;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandlerConfigure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;

class LunaServiceProvider extends ServiceProvider
{
    /**
     * @var LunaModuleConfigure[]
     */
    private array $modules = [];

    /**
     * @var ServiceProvider[]
     */
    protected array $instances = [];

    /**
     * @throws BindingResolutionException
     */
    final public function register(): void
    {
        $this->registerLunaPrototypeCommands();
        $this->registerDefaultModules();

        $this->customRegister();

        $this->registerLunaModules();
    }

    /**
     * @throws BindingResolutionException
     */
    protected function registerDefaultModules(): void
    {
        $this->registerModule(LunaConfigurationConfigure::create()->build());
        $this->registerModule(LunaExceptionConfigure::create()->build());
        $this->registerModule(LunaBusinessConfigure::create()->build());
        $this->registerModule(LunaHandlerConfigure::create()->build());
        $this->registerModule(LunaApplicationConfigure::create()->build());
    }

    public function customRegister(): void
    {
        //
    }

    final public function registerModule(LunaModuleConfigure $configure): static
    {
        $this->modules[$configure->name()] = $configure;
        return $this;
    }

    private function registerLunaModules(): void
    {
        $this->instances = [];

        foreach ($this->modules as $module) {
            if ($module instanceof Closure) {
                $this->app->instance($module::class, $module = $this->app->call($module));
            } else {
                $this->app->instance($module::class, $module);
            }

            $module->register($this->app);

            $provider = $module->serviceProvider();
            if ($provider) {
                $this->instances[] = $this->app->register($provider);
            }
        }
    }

    final protected function registerLunaPrototypeCommands(): void
    {
        // 注册命令
        if ($this->app->runningInConsole()) {
            $this->commands([
                Consoles\AppCurrent::class,
                Consoles\AppEnvironment::class,
                Consoles\AppInstall::class,
            ]);
        }
    }

    public function customBoot(): void
    {

    }

    /**
     * @throws BindingResolutionException
     */
    private function bootLunaPrototypeModules(): void
    {
        foreach ($this->modules as $module) {
            $module = $this->app->make($module::class);
            $module->boot($this->app);
        }
    }

    /**
     * @throws BindingResolutionException
     */
    final public function boot(): void
    {
        $this->publishesMigrations([
            __DIR__ . '/migrations' => database_path('migrations'),
        ]);

        $this->bootLunaPrototypeModules();
        $this->customBoot();
    }
}
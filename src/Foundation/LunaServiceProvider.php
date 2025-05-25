<?php

namespace Dybasedev\LunaPrototype\Foundation;

use Closure;
use Dybasedev\LunaPrototype\Foundation\Configuration\LunaConfigurationConfigure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;

class LunaServiceProvider extends ServiceProvider
{
    /**
     * @var LunaModuleConfigure[]
     */
    private array $modules = [];

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
    protected function registerDefaultModules():void
    {
        $this->registerModule(LunaConfigurationConfigure::create()->build());
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
        foreach ($this->modules as $module) {
            if ($module instanceof Closure) {
                $this->app->singleton($module::class, $module);
            } else {
                $this->app->instance($module::class, $module);
            }

            $module->register($this->app);
        }
    }

    final protected function registerLunaPrototypeCommands(): void
    {
        // 注册命令
        if ($this->app->runningInConsole()) {
            $this->commands([
                Consoles\AppCurrent::class,
                Consoles\AppEnvironment::class,
            ]);
        }
    }
}
<?php

namespace Dybasedev\LunaPrototype\Foundation;

use Closure;
use Dybasedev\LunaPrototype\Foundation\BusinessEvent\LunaBusinessEventConfigure;
use Dybasedev\LunaPrototype\Foundation\Configuration\LunaConfigurationConfigure;
use Dybasedev\LunaPrototype\Foundation\Exception\LunaExceptionConfigure;
use Dybasedev\LunaPrototype\Foundation\Handler\LunaHandlerConfigure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

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
        $this->registerModule(LunaBusinessEventConfigure::create()->build());
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

    /**
     * 扩展已经配置的模块
     *
     * @param Closure $register
     * @return $this
     */
    final public function extendModule(Closure $register): static
    {
        $configure = $this->app->call($register);

        if (!$configure) {
            throw new RuntimeException('Must return configure instance.');
        }

        if (!($configure instanceof LunaModuleConfigure)) {
            throw new RuntimeException('Must return configure.');
        }

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
                // 检查模型依赖
                if ($dependencies = $module->dependencies()) {
                    if (!array_all($dependencies, fn($dependency) => isset($this->modules[$dependency]))) {
                        throw new RuntimeException(sprintf('[%s]: Dependency module not found.', $module->name()));
                    }
                }

                $this->app->instance($module::class, $module);
            }

            $module->register($this->app);

            $provider = $module->serviceProvider();
            if ($provider) {
                $this->instances[] = $this->app->register($provider);
            }
        }

        $this->app->singleton('luna.registered-modules', fn() => $this->modules);
    }

    final protected function registerLunaPrototypeCommands(): void
    {
        // 注册命令
        if ($this->app->runningInConsole()) {
            $this->commands([
                Consoles\AppCurrent::class,
                Consoles\AppEnvironment::class,
                Consoles\AppInstall::class,
                Consoles\AppReinstall::class,
                Consoles\AppBackup::class,
                Consoles\AppPublishModels::class,
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
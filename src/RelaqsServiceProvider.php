<?php

namespace Shanginn\Relaqs;

use Illuminate\Support\ServiceProvider;
use Shanginn\Relaqs\Eloquent\Helpers\Relaqser;

class RelaqsServiceProvider extends ServiceProvider
{
    /**
     * Register all
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/relaqs.php',
            'relaqs'
        );

        $this->registerClassAliases();

        $this->bindClasses();
    }

    protected function registerClassAliases()
    {
        $classAliases = [
            'relaqs.relaqser' => Relaqser::class,
        ];

        foreach ($classAliases as $key => $aliases) {
            foreach ((array) $aliases as $alias) {
                $this->app->alias($key, $alias);
            }
        }
    }

    protected function bindClasses()
    {
        $this->app->singleton('relaqs.relaqser', function ($app) {
            return new Relaqser;
        });
    }
}

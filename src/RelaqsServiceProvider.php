<?php

namespace Shanginn\Relaqs;

use Illuminate\Support\ServiceProvider;
use Shanginn\Relaqs\Routing\Cruder;
use Shanginn\Relaqs\Routing\Middleware\RelaqsBindings;
use Shanginn\Relaqs\Routing\ResourceRegistrar;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Foundation\AliasLoader;
use Shanginn\Relaqs\Http\Requests\CrudRequest;

class RelaqsServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    //protected $defer = false;

    /**
     * Register all
     *
     * @return void
     */
    public function register()
    {
//        $this->registerClassAliases();
//
//        $this->registerMiddleware();
//
//        $this->bindClasses();
//
//        $this->registerFacades();
//
//        $this->prependRelaqsMiddleware($this->app->make(Kernel::class));
    }

    protected function prependRelaqsMiddleware(\Illuminate\Foundation\Http\Kernel $kernel)
    {
        //kernel->prependMiddleware('crud.binding');
    }

    protected function bindClasses()
    {
        $this->app->singleton('crud.resource', function ($app) {
            return new ResourceRegistrar($app->make('router'));
        });

        $this->app->singleton('crud.binding', function ($app) {
            return new RelaqsBindings($app->router, $app);
        });

        $this->app->singleton('crud.cruder', function ($app) {
            return new Cruder($app->router);
        });
    }

    protected function registerFacades()
    {
        $facades = [
            'Cruder' => \Shanginn\Relaqs\Facades\Cruder::class,
            'Relaqs' => \Shanginn\Relaqs\Routing\Relaqs::class,
            'CrudRequest' => \Shanginn\Relaqs\Http\Requests\CrudRequest::class
        ];

        AliasLoader::getInstance($facades)->register();
    }

    protected function registerMiddleware()
    {
        $this->aliasMiddleware('crud.binding', RelaqsBindings::class);

        $router = $this->app['router'];

        if (($offset = array_search(SubstituteBindings::class, $router->middlewarePriority)) !== false) {
            $router->middlewarePriority = array_merge(
                array_slice($router->middlewarePriority, 0, $offset),
                (array) RelaqsBindings::class,
                array_slice($router->middlewarePriority, $offset)
            );
        };
    }

    protected function registerClassAliases()
    {
        $aliases = [
            'crud.resource' => [
                \Dingo\Api\Routing\ResourceRegistrar::class,
                \Illuminate\Routing\ResourceRegistrar::class
            ],
            'crud.binding' => RelaqsBindings::class,
            'crud.cruder' => Cruder::class,
        ];

        foreach ($aliases as $key => $aliases) {
            foreach ((array) $aliases as $alias) {
                $this->app->alias($key, $alias);
            }
        }
    }

    /**
     * Register a short-hand name for a middleware. For Compatability
     * with Laravel < 5.4 check if aliasMiddleware exists since this
     * method has been renamed.
     *
     * @param string $name
     * @param string $class
     *
     * @return mixed
     */
    protected function aliasMiddleware($name, $class)
    {
        /** @var \Dingo\Api\Routing\Router|\Illuminate\Routing\Router $router */
        $router = $this->app['router'];

        if (method_exists($router, 'aliasMiddleware')) {
            return $router->aliasMiddleware($name, $class);
        }

        return $router->middleware($name, $class);
    }
}

<?php

namespace Shanginn\Relaqs\Facades;

use Illuminate\Support\Facades\Facade;

class Relaqser extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'relaqs.relaqser';
    }
}

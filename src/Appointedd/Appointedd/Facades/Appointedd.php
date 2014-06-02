<?php namespace Appointedd\Appointedd\Facades;

use Illuminate\Support\Facades\Facade;

class Appointedd extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'appointedd'; }

}

<?php
declare(strict_types = 1);

namespace Telelogger;

use Illuminate\Support\Facades\Facade as IlluminateFacade;


class Facade extends IlluminateFacade
{
    protected static function getFacadeAccessor()
    {
        return 'telelogger';
    }
}

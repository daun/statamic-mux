<?php

namespace Daun\StatamicMux\Facades;

use Daun\StatamicMux\Support\Logger;
use Illuminate\Support\Facades\Facade;

/**
 * @see \Daun\StatamicMux\Support\Logger
 */
class Log extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Logger::class;
    }
}

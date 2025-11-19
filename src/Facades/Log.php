<?php

namespace Daun\StatamicMux\Facades;

use Daun\StatamicMux\Support\Logging\LoggerInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @see \Daun\StatamicMux\Support\Logging\Logger
 */
class Log extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LoggerInterface::class;
    }
}

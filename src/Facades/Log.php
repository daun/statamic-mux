<?php

namespace Daun\StatamicMux\Facades;

use Daun\StatamicMux\Support\Logging\LoggerInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void emergency(\Stringable|string $message, mixed[] $context = [])
 * @method static void alert(\Stringable|string $message, mixed[] $context = [])
 * @method static void critical(\Stringable|string $message, mixed[] $context = [])
 * @method static void error(\Stringable|string $message, mixed[] $context = [])
 * @method static void warning(\Stringable|string $message, mixed[] $context = [])
 * @method static void notice(\Stringable|string $message, mixed[] $context = [])
 * @method static void info(\Stringable|string $message, mixed[] $context = [])
 * @method static void debug(\Stringable|string $message, mixed[] $context = [])
 * @method static void log(mixed $level, \Stringable|string $message, mixed[] $context = [])
 *
 * @see \Daun\StatamicMux\Support\Logging\LoggerInterface
 */
class Log extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LoggerInterface::class;
    }
}

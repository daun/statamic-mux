<?php

namespace Daun\StatamicMux\Support\Logging\Processors;

use RedactSensitive\RedactSensitiveProcessor;

use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\FilterHandler;
use Monolog\Handler\WhatFailureGroupHandler;
use Monolog\Level;

final class ErrorForwarder
{
    public function __invoke(Logger $logger): void
    {
        // Resolve the app’s default channel’s underlying Monolog handlers
        $defaultMonolog = Log::channel(config('logging.default'))->getLogger();
        $defaultHandlers = $defaultMonolog->getHandlers();

        // Wrap each of its handlers with a filter handler restricting to error+
        $filtered = array_map(function ($handler) {
            return new FilterHandler($handler, Level::Error);
        }, $defaultHandlers);

        ray($filtered)->label('Forwarding error+ to app logger handlers');

        // Our logger should already have a WhatFailureGroupHandler as its single handler
        $monolog = $logger->getLogger();
        $handlers = $monolog->getHandlers();
        $groupHandlers = array_filter($handlers, function ($handler) {
            return $handler instanceof WhatFailureGroupHandler;
        });
        $groupHandler = $groupHandlers[array_key_first($groupHandlers)] ?? null;
        if ($groupHandler) {
            // Replace its internal handlers with our filtered ones
            // WhatFailureGroupHandler accepts handlers via constructor only, so recreate it
            $ref = new \ReflectionClass(WhatFailureGroupHandler::class);
            $new = $ref->newInstanceArgs([$filtered]);

            // Swap handler: remove old, push new
            $monolog->popHandler();
            $monolog->pushHandler($new);
        } else {
            // Fallback: if no group handler found, just push filtered handlers
            foreach ($filtered as $h) {
                $monolog->pushHandler($h);
            }
        }
    }
}

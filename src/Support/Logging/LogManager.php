<?php

namespace Daun\StatamicMux\Support\Logging;

use Daun\StatamicMux\Support\Logging\Processors\ContextScrubber;
use Daun\StatamicMux\Support\Logging\Processors\ErrorForwarder;
use Illuminate\Log\LogManager as IlluminateLog;
use Monolog\Handler\WhatFailureGroupHandler;
use Psr\Log\LoggerInterface as PsrLogger;
use Psr\Log\NullLogger;

class LogManager
{
    public function __construct(
        protected IlluminateLog $log,
        protected ?string $channel = null,
        protected bool $enabled = true,
    ) {
        $this->registerStack();
    }

    public function resolveStack(): PsrLogger
    {
        if (! $this->enabled) {
            return new NullLogger;
        }

        try {
            return $this->log->channel('mux_stack');
        } catch (\Throwable $e) {
            return $this->log; // default app logger
        }
    }

    public function resolveChannel(): PsrLogger
    {
        if (! $this->enabled) {
            return new NullLogger;
        }

        try {
            return $this->log->channel($this->channel);
        } catch (\Throwable $e) {
            return $this->log; // default app logger
        }
    }

    public function registerStack(): void
    {
        // 1) Package channel
        // Can be overwritten by defining 'mux' channel in app config or by setting
        // the mux.logging.channel to something else.
        if (! config('logging.channels.mux')) {
            config()->set('logging.channels.mux', [
                'driver' => 'single',
                'path' => storage_path('logs/mux.log'),
                'level' => config('mux.logging.level', 'warning'),
                'replace_placeholders' => true,
            ]);
        }

        // 2) Passthrough channel to the app’s default, restricted to >= error
        config()->set('logging.channels.mux_forward_errors', [
            'driver' => 'monolog',
            'handler' => WhatFailureGroupHandler::class,
            'with' => ['handlers' => []],
            'tap' => [ErrorForwarder::class],
        ]);

        // 3) Final stack exposed by the package
        config()->set('logging.channels.mux_stack', [
            'driver' => 'stack',
            'channels' => [$this->channel, 'mux_forward_errors'],
            'ignore_exceptions' => false,
            'tap' => [ContextScrubber::class],
        ]);
    }
}

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
        protected string $channel = 'mux',
        protected bool $enabled = true,
    ) {
        $this->registerChannels();
    }

    /**
     * Resolve the `stack` logger that combines the package channel
     * and the error forwarder channel.
     */
    public function resolveStack(): PsrLogger
    {
        return $this->enabled
            ? $this->log->channel('mux_stack')
            : new NullLogger;
    }

    /**
     * Resolve the core package channel that can be customized or overridden.
     */
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

    /**
     * Register the different logging channels used by the package:
     * 1. The mux package channel (customizable via config)
     * 2. The error forwarder channel
     * 3. The sdk channel for Mux SDK debug logs
     */
    public function registerChannels(): void
    {
        // 1) Package channel
        // Can be customized by defining 'mux' channel in app config or
        // by setting `mux.logging.channel` to something else.
        if (! config('logging.channels.mux')) {
            config()->set('logging.channels.mux', [
                'driver' => 'single',
                'path' => storage_path('logs/mux.log'),
                'level' => config('mux.logging.level', 'warning'),
                'replace_placeholders' => true,
            ]);
        }

        // 2) Passthrough channel to the app’s default, restricted to >= error
        config()->set('logging.channels.mux_errors', [
            'driver' => 'monolog',
            'handler' => WhatFailureGroupHandler::class,
            'with' => ['handlers' => []],
            'tap' => [ErrorForwarder::class],
        ]);

        // 3) Final stack exposed and used by the package
        config()->set('logging.channels.mux_stack', [
            'driver' => 'stack',
            'channels' => [$this->channel, 'mux_errors'],
            'tap' => [ContextScrubber::class],
            'ignore_exceptions' => false,
        ]);
    }
}

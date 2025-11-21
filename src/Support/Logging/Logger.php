<?php

namespace Daun\StatamicMux\Support\Logging;

use Illuminate\Log\LogManager;
use Psr\Log\LoggerInterface as PsrLogger;
use Psr\Log\NullLogger;

class Logger
{
    public function __construct(
        protected LogManager $log,
        protected ?string $channel = null,
        protected bool $enabled = true,
    ) {
        $this->registerStack();
    }

    public function resolveChannel(): PsrLogger
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

    public function registerStack(): void
    {
        // 1) Package channel
        // Can be overwritten by defining 'mux' channel in app config or by setting
        // the mux.logging.channel to something else.
        if (! config('logging.channels.mux')) {
            config()->set('logging.channels.mux', [
                'driver' => 'daily',
                'path' => storage_path('logs/mux.log'),
                'level' => config('mux.logging.level', 'warning'),
                'days' => config('logging.channels.daily.days', 14),
                'replace_placeholders' => true,
                'tap' => [Scrubber::class],
            ]);
        }

        // 2) Passthrough channel to the app’s default, restricted to >= error
        config()->set('logging.channels.mux_errors', [
            ...config(
                sprintf('logging.channels.%s', config('logging.default', 'stack')),
                ['driver' => 'stack', 'channels' => ['single']]
            ),
            'level' => 'error',
        ]);

        // 3) Final stack exposed by the package
        config()->set('logging.channels.mux_stack', [
            'driver' => 'stack',
            'channels' => [$this->channel, 'mux_errors'],
            'ignore_exceptions' => false,
            'tap' => [Scrubber::class],
        ]);
    }
}

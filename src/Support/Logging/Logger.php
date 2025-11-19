<?php

namespace Daun\StatamicMux\Support\Logging;

use Illuminate\Log\LogManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Logger
{
    public function __construct(
        protected LogManager $log,
        protected ?string $channel = null,
        protected bool $enabled = true,
    ) {
        $this->registerChannel();
    }

    public function resolveChannel(): LoggerInterface
    {
        if (! $this->enabled) {
            return new NullLogger();
        }

        try {
            return $this->log->channel($this->channel);
        } catch (\Throwable $e) {
            return $this->log; // default app logger
        }
    }

    public function registerChannel(): void
    {
        if (config('logging.channels.mux')) {
            return;
        }

        config()->set('logging.channels.mux', [
            'driver' => 'daily',
            'path' => storage_path('logs/mux.log'),
            'level' => config('mux.logging.level', 'warning'),
            'days' => config('logging.channels.daily.days', 14),
            'replace_placeholders' => true,
        ]);
    }
}

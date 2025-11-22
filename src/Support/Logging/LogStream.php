<?php

namespace Daun\StatamicMux\Support\Logging;

use Psr\Log\LoggerInterface;

class LogStream
{
    public const PROTOCOL = 'mux';

    /** @var resource|null */
    public $context;

    private static ?LoggerInterface $logger = null;

    // Called from your service provider after logger is resolvable
    public static function register(LoggerInterface $logger): void
    {
        self::$logger = $logger;

        if (in_array(self::PROTOCOL, stream_get_wrappers(), true)) {
            stream_wrapper_unregister(self::PROTOCOL);
        }

        stream_wrapper_register(self::PROTOCOL, self::class, STREAM_IS_URL);
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        return true;
    }

    public function stream_write(string $data): int
    {
        if (! self::$logger) {
            return strlen($data);
        }

        // Normalize line endings and split by lines to avoid giant single log records
        $lines = preg_split('/\r\n|\r|\n/', $data);
        foreach ($lines as $line) {
            if ($line = trim($line)) {
                self::$logger->debug($line);
            }
        }

        return strlen($data);
    }

    public function stream_close(): void {}

    public function stream_flush(): bool
    {
        return true;
    }

    public function stream_eof(): bool
    {
        return true;
    }

    public function stream_stat(): array
    {
        return [];
    }
}

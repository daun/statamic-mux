<?php

namespace Daun\StatamicMux\Support\Logging;

use Psr\Log\LoggerInterface;

class LogStream
{
    /** @var resource|null */
    public $context;

    private ?string $protocol = null;

    /** @var array<string, LoggerInterface> */
    private static array $loggers = [];

    // Called from your service provider after logger is resolved
    public static function register(string $protocol, LoggerInterface $logger): void
    {
        self::$loggers[$protocol] = $logger;

        if (in_array($protocol, stream_get_wrappers(), true)) {
            stream_wrapper_unregister($protocol);
        }

        stream_wrapper_register($protocol, self::class, STREAM_IS_URL);
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        // Extract protocol from path (e.g., "mux://..." -> "mux")
        if (preg_match('#^([a-z]+)://#', $path, $matches)) {
            $this->protocol = $matches[1];
            return true;
        } else {
            return false;
        }
    }

    public function stream_write(string $data): int
    {
        $logger = self::$loggers[$this->protocol] ?? null;
        if (! $logger) {
            return strlen($data);
        }

        // Normalize line endings
        $lines = preg_split('/\r\n|\r|\n/', $data);
        $message = join("\n", $lines);

        $logger->debug($message);

        // Split by lines to avoid giant single log records
        // foreach ($lines as $line) {
        //     if ($line = trim($line)) {
        //         $logger->debug($line);
        //     }
        // }

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

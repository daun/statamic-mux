<?php

namespace Daun\StatamicMux\Support\Logging;

use RedactSensitive\RedactSensitiveProcessor;

final class Scrubber
{
    protected array $sensitiveKeys = [
        'token_id' => 4,
        'token_secret' => 4,
        'key_id' => 4,
        'private_key' => 8,
    ];

    /**
     * @param  \Monolog\Logger  $logger
     */
    public function __invoke($logger): void
    {
        $processor = new RedactSensitiveProcessor($this->sensitiveKeys, lengthLimit: 64);

        $logger->pushProcessor($processor);
    }
}

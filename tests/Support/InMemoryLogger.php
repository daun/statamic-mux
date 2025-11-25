<?php

namespace Tests\Support;

use Psr\Log\AbstractLogger;

class InMemoryLogger extends AbstractLogger
{
    /**
     * @var array<int, array{level:string,message:string,context:array}>
     */
    public array $records = [];

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * @return array<int, array{level:string,message:string,context:array}>
     */
    public function recordsByLevel(string $level): array
    {
        return array_values(array_filter(
            $this->records,
            static fn (array $record) => $record['level'] === $level,
        ));
    }
}

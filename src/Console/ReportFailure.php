<?php

namespace Daun\StatamicMux\Console;

use Daun\StatamicMux\Mux\Enums\ReconciliationAction;

final readonly class ReportFailure
{
    public function __construct(
        public string $error,
        public ?ReconciliationAction $action = null,
        public ?string $id = null,
    ) {}

    public static function make(string $error, ?ReconciliationAction $action = null, ?string $id = null): self
    {
        return new self($error, $action, $id);
    }

    /**
     * @return array{action: ?string, id: ?string, error: string}
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action?->value,
            'id' => $this->id,
            'error' => $this->error,
        ];
    }
}

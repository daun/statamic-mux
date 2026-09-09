<?php

namespace Daun\StatamicMux\Console;

use Daun\StatamicMux\Mux\Enums\ReconciliationAction;
use Daun\StatamicMux\Mux\Enums\ReconciliationState;

final readonly class ReportRecord
{
    /**
     * @param  string  $id  Canonical identifier, e.g. an asset ID or a Mux asset ID
     * @param  ?string  $label  Shortened display label, defaults to the ID
     * @param  array<string, string>  $diagnostics  Extra key/value rows shown at -vv
     */
    public function __construct(
        public ReconciliationAction $action,
        public string $id,
        public ?ReconciliationState $state = null,
        public ?string $reason = null,
        public ?string $label = null,
        public RecordStatus $status = RecordStatus::Planned,
        public ?string $error = null,
        public array $diagnostics = [],
    ) {}

    /**
     * @param  array<string, string>  $diagnostics
     */
    public static function make(
        ReconciliationAction $action,
        string $id,
        ?ReconciliationState $state = null,
        ?string $reason = null,
        ?string $label = null,
        array $diagnostics = [],
    ): self {
        return new self(
            action: $action,
            id: $id,
            state: $state,
            reason: $reason,
            label: $label,
            diagnostics: $diagnostics,
        );
    }

    public function succeeded(): self
    {
        return $this->withStatus(RecordStatus::Succeeded);
    }

    public function failed(?string $error = null): self
    {
        return $this->withStatus(RecordStatus::Failed, $error);
    }

    public function withStatus(RecordStatus $status, ?string $error = null): self
    {
        return new self(
            action: $this->action,
            id: $this->id,
            state: $this->state,
            reason: $this->reason,
            label: $this->label,
            status: $status,
            error: $error ?? $this->error,
            diagnostics: $this->diagnostics,
        );
    }

    public function withAction(ReconciliationAction $action, ?string $reason = null): self
    {
        return new self(
            action: $action,
            id: $this->id,
            state: $this->state,
            reason: $reason ?? $this->reason,
            label: $this->label,
            status: $this->status,
            error: $this->error,
            diagnostics: $this->diagnostics,
        );
    }

    /**
     * @param  array<string, string>  $diagnostics
     */
    public function withDiagnostics(array $diagnostics): self
    {
        return new self(
            action: $this->action,
            id: $this->id,
            state: $this->state,
            reason: $this->reason,
            label: $this->label,
            status: $this->status,
            error: $this->error,
            diagnostics: [...$this->diagnostics, ...$diagnostics],
        );
    }

    public function display(bool $full = false): string
    {
        return $full ? $this->id : ($this->label ?? $this->id);
    }

    public function reason(): ?string
    {
        return $this->reason ?? $this->state?->reason();
    }

    public function token(Tense $tense): string
    {
        return $this->status->isFailed()
            ? ReconciliationAction::FAILED_TOKEN
            : $this->action->token($tense);
    }

    /**
     * @return array{action: string, id: string, state: ?string, reason: ?string}
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action->value,
            'id' => $this->id,
            'state' => $this->state?->value,
            'reason' => $this->reason,
        ];
    }
}

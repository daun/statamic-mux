<?php

namespace Daun\StatamicMux\Console;

use Daun\StatamicMux\Mux\Enums\ReconciliationAction;

final class CommandReport
{
    public const SUCCESS = 0;

    public const FAILURE = 1;

    /** @var list<string> */
    protected array $containers = [];

    protected ?int $locals = null;

    protected ?int $remotes = null;

    /** @var list<array{label: string, value: ?string}> */
    protected array $context = [];

    /** @var array<string, int> */
    protected array $counts = [];

    /** @var array<string, string> */
    protected array $reasons = [];

    /** @var list<ReportRecord> */
    protected array $records = [];

    /** @var list<Advisory> */
    protected array $advisories = [];

    /** @var list<ReportFailure> */
    protected array $failures = [];

    protected int $exitCode = self::SUCCESS;

    /** @var ?array{level: AdvisoryLevel, message: string} */
    protected ?array $summary = null;

    public function __construct(
        protected string $command,
        protected Tense $tense = Tense::Planned,
    ) {}

    public static function make(string $command, Tense $tense = Tense::Planned): self
    {
        return new self($command, $tense);
    }

    public function command(): string
    {
        return $this->command;
    }

    public function subject(): string
    {
        $segments = explode(':', $this->command);

        return ucfirst(str_replace('-', ' ', (string) end($segments)));
    }

    public function tense(): Tense
    {
        return $this->tense;
    }

    public function dryRun(): bool
    {
        return $this->tense->isPlanned();
    }

    /**
     * @param  list<string>  $containers
     */
    public function scope(array $containers = [], ?int $locals = null, ?int $remotes = null): self
    {
        $this->containers = $containers;
        $this->locals = $locals;
        $this->remotes = $remotes;

        return $this;
    }

    /** @return list<string> */
    public function containers(): array
    {
        return $this->containers;
    }

    public function locals(): ?int
    {
        return $this->locals;
    }

    public function remotes(): ?int
    {
        return $this->remotes;
    }

    public function context(string $label, ?string $value = null): self
    {
        $this->context[] = ['label' => $label, 'value' => $value];

        return $this;
    }

    /** @return list<array{label: string, value: ?string}> */
    public function contextRows(): array
    {
        return $this->context;
    }

    /** Count records the command does not identify individually. */
    public function count(ReconciliationAction $action, int $count, ?string $reason = null): self
    {
        if ($count <= 0) {
            return $this;
        }

        $this->counts[$action->value] = ($this->counts[$action->value] ?? 0) + $count;

        if ($reason !== null) {
            $this->reasons[$action->value] = $reason;
        }

        return $this;
    }

    public function record(ReportRecord $record): self
    {
        $this->records[] = $record;

        return $this;
    }

    /**
     * @param  iterable<ReportRecord>  $records
     */
    public function recordMany(iterable $records): self
    {
        foreach ($records as $record) {
            $this->record($record);
        }

        return $this;
    }

    /** @return list<ReportRecord> */
    public function records(): array
    {
        return $this->records;
    }

    /** @return list<ReportRecord> */
    public function recordsFor(ReconciliationAction $action): array
    {
        return array_values(array_filter(
            $this->records,
            fn (ReportRecord $record) => $record->action === $action,
        ));
    }

    public function advisory(Advisory $advisory): self
    {
        $this->advisories[] = $advisory;

        return $this;
    }

    /** @return list<Advisory> */
    public function advisories(): array
    {
        return $this->advisories;
    }

    public function failure(ReportFailure $failure): self
    {
        $this->failures[] = $failure;
        $this->exitCode = self::FAILURE;

        return $this;
    }

    /** @return list<ReportFailure> */
    public function failures(): array
    {
        return $this->failures;
    }

    public function summary(AdvisoryLevel $level, string $message): self
    {
        $this->summary = ['level' => $level, 'message' => $message];

        return $this;
    }

    public function abort(string $message): self
    {
        $this->summary = ['level' => AdvisoryLevel::Error, 'message' => $message];
        $this->exitCode = self::FAILURE;

        return $this;
    }

    /** @return ?array{level: AdvisoryLevel, message: string} */
    public function summaryOverride(): ?array
    {
        return $this->summary;
    }

    public function exitCode(): int
    {
        return $this->exitCode;
    }

    public function fail(): self
    {
        $this->exitCode = self::FAILURE;

        return $this;
    }

    public function failed(): bool
    {
        return $this->exitCode !== self::SUCCESS;
    }

    /** Failed records are reported under `failures`, never under their verb. */
    public function countOf(ReconciliationAction $action): int
    {
        $records = array_filter(
            $this->recordsFor($action),
            fn (ReportRecord $record) => ! $record->status->isFailed(),
        );

        return ($this->counts[$action->value] ?? 0) + count($records);
    }

    /**
     * A fixed schema: `--json` consumers never have to check whether a key exists.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = [];

        foreach (ReconciliationAction::ordered() as $action) {
            $counts[$action->value] = $this->countOf($action);
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    public function visibleCounts(): array
    {
        $counts = [];

        foreach (ReconciliationAction::visible() as $action) {
            if ($count = $this->countOf($action)) {
                $counts[$action->value] = $count;
            }
        }

        return $counts;
    }

    public function hasVisibleActions(): bool
    {
        return $this->visibleCounts() !== [];
    }

    public function hasInventory(): bool
    {
        return $this->locals !== null || $this->remotes !== null;
    }

    public function inventoryIsEmpty(): bool
    {
        return $this->hasInventory() && ($this->locals ?? 0) === 0 && ($this->remotes ?? 0) === 0;
    }

    public function hasPlan(): bool
    {
        return $this->hasInventory() || $this->counts !== [] || $this->records !== [];
    }

    /**
     * @return array<string, int>
     */
    public function reasonBreakdown(ReconciliationAction $action): array
    {
        $breakdown = [];

        foreach ($this->recordsFor($action) as $record) {
            if ($reason = $record->reason()) {
                $breakdown[$reason] = ($breakdown[$reason] ?? 0) + 1;
            }
        }

        if ($declared = $this->reasons[$action->value] ?? null) {
            $breakdown[$declared] = ($breakdown[$declared] ?? 0) + ($this->counts[$action->value] ?? 0);
        }

        return $breakdown;
    }

    /**
     * @return array{command: string, dry_run: bool, tense: string, scope: array{containers: list<string>, locals: int, remotes: int}, context: array<string, ?string>, plan: array<string, int>, records: list<array{action: string, id: string, state: ?string, reason: ?string}>, advisories: list<array{level: string, code: string, records: list<string>}>, failures: list<array{action: ?string, id: ?string, error: string}>, exit_code: int}
     */
    public function toArray(): array
    {
        return [
            'command' => $this->command,
            'dry_run' => $this->dryRun(),
            'tense' => $this->tense->value,
            'scope' => [
                'containers' => $this->containers,
                'locals' => $this->locals ?? 0,
                'remotes' => $this->remotes ?? 0,
            ],
            'context' => array_reduce(
                $this->context,
                function (array $rows, array $row) {
                    $rows[$row['label']] = $row['value'];

                    return $rows;
                },
                [],
            ),
            'plan' => $this->counts(),
            'records' => array_map(fn (ReportRecord $record) => $record->toArray(), $this->records),
            'advisories' => array_map(fn (Advisory $advisory) => $advisory->toArray(), $this->advisories),
            'failures' => array_map(fn (ReportFailure $failure) => $failure->toArray(), $this->failures),
            'exit_code' => $this->exitCode,
        ];
    }

    public function toJson(int $flags = 0): string
    {
        return (string) json_encode($this->toArray(), $flags | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

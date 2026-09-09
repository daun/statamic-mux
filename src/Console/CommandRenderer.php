<?php

namespace Daun\StatamicMux\Console;

use Daun\StatamicMux\Mux\Enums\ReconciliationAction;
use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use Symfony\Component\Console\Formatter\OutputFormatter;

final class CommandRenderer
{
    protected Factory $components;

    public function __construct(
        protected OutputStyle $output,
    ) {
        $this->components = new Factory($output);
    }

    public function render(CommandReport $report): void
    {
        if ($this->output->isQuiet()) {
            return;
        }

        $this->context($report);
        $this->plan($report);
        $this->details($report);
        $this->advisories($report);
        $this->failures($report);
        $this->summary($report);
    }

    protected function context(CommandReport $report): void
    {
        $this->output->newLine();
        $this->output->writeln('  <options=bold>'.$this->escape($report->command()).'</>');
        $this->output->newLine();

        if ($containers = $report->containers()) {
            $this->components->twoColumnDetail('Containers', implode(', ', array_map($this->escape(...), $containers)));
        }

        if (($locals = $report->locals()) !== null) {
            $this->components->twoColumnDetail('Local videos', (string) $locals);
        }

        if (($remotes = $report->remotes()) !== null) {
            $this->components->twoColumnDetail('Mux assets', (string) $remotes);
        }

        foreach ($report->contextRows() as $row) {
            $this->components->twoColumnDetail(
                $this->escape($row['label']),
                $row['value'] === null ? null : $this->escape($row['value']),
            );
        }
    }

    protected function plan(CommandReport $report): void
    {
        if (! $report->hasPlan()) {
            return;
        }

        $this->heading('Plan', $report->dryRun() ? 'DRY RUN' : null);

        $counts = $report->visibleCounts();

        if ($counts === []) {
            $this->output->writeln('  <fg=gray>'.$this->emptyState($report).'</>');
            $this->output->newLine();

            return;
        }

        foreach ($counts as $value => $count) {
            $action = ReconciliationAction::from($value);
            $breakdown = $report->reasonBreakdown($action);

            if (count($breakdown) > 1) {
                $this->components->twoColumnDetail($this->escape($action->label()), (string) $count);

                foreach ($breakdown as $reason => $reasonCount) {
                    // Termwind collapses ordinary leading spaces inside component content.
                    $indent = str_repeat("\u{00A0}", 10);

                    $this->components->twoColumnDetail(
                        '<fg=gray>'.$indent.$this->escape($reason).'</>',
                        '<fg=gray>'.$reasonCount.'</>',
                    );
                }

                continue;
            }

            $reason = (string) array_key_first($breakdown);

            $this->components->twoColumnDetail(
                str_pad($this->escape($action->label()), 10).($reason ? ' <fg=gray>'.$this->escape($reason).'</>' : ''),
                (string) $count,
            );
        }
    }

    protected function details(CommandReport $report): void
    {
        if (! $this->output->isVerbose() || $report->records() === []) {
            return;
        }

        $full = $this->output->isVeryVerbose();
        $rendered = false;

        foreach (ReconciliationAction::visible() as $action) {
            $records = $report->recordsFor($action);

            if ($records === []) {
                continue;
            }

            if (! $rendered) {
                $this->heading('Detail');
                $rendered = true;
            }

            $this->output->writeln(sprintf('  <fg=%s>%s</>', $action->color(), $action->label()));

            foreach ($records as $record) {
                $reason = $record->reason();

                $this->components->twoColumnDetail(
                    $this->escape($record->display($full)).($reason ? ' <fg=gray>'.$this->escape($reason).'</>' : ''),
                    sprintf('<fg=%s>%s</>', $this->tokenColor($record), $record->token($report->tense())),
                );

                if ($record->error) {
                    $this->output->writeln('    <fg=red>'.$this->escape($record->error).'</>');
                }

                if ($full) {
                    foreach ($record->diagnostics as $label => $value) {
                        $this->output->writeln('    <fg=gray>'.$this->escape($label).': '.$this->escape($value).'</>');
                    }
                }
            }
        }
    }

    protected function advisories(CommandReport $report): void
    {
        foreach ($report->advisories() as $advisory) {
            match ($advisory->level) {
                AdvisoryLevel::Info => $this->components->info($this->escape($advisory->message)),
                AdvisoryLevel::Warn => $this->components->warn($this->escape($advisory->message)),
                AdvisoryLevel::Error => $this->components->error($this->escape($advisory->message)),
            };

            if ($advisory->records !== []) {
                $this->components->bulletList(array_map($this->escape(...), $advisory->records));
            }

            if ($advisory->hint) {
                $this->output->writeln('   <fg=gray>'.$this->escape($advisory->hint).'</>');
                $this->output->newLine();
            }
        }
    }

    /** Failures without a record, e.g. a handled precondition, are carried by the summary instead. */
    protected function failures(CommandReport $report): void
    {
        $failures = array_values(array_filter(
            $report->failures(),
            fn (ReportFailure $failure) => $failure->id !== null,
        ));

        if ($failures === []) {
            return;
        }

        $this->components->error(count($failures) === 1 ? '1 failure' : count($failures).' failures');

        $this->components->bulletList(array_map(
            fn (ReportFailure $failure) => $this->escape($failure->id ? "{$failure->id}: {$failure->error}" : $failure->error),
            $failures,
        ));
    }

    protected function summary(CommandReport $report): void
    {
        if ($override = $report->summaryOverride()) {
            $this->badge($override['level'], $override['message']);

            return;
        }

        $body = $this->sentences($report);
        $failures = count($report->failures());

        if ($report->dryRun()) {
            $message = $report->hasVisibleActions()
                ? "{$body} pending."
                : "{$report->subject()} — {$body}.";

            $this->badge($failures ? AdvisoryLevel::Warn : AdvisoryLevel::Info, $message);

            return;
        }

        if ($failures) {
            $noun = $failures === 1 ? 'failure' : 'failures';
            $this->badge(AdvisoryLevel::Warn, "{$report->subject()} finished with {$failures} {$noun} — {$body}.");

            return;
        }

        $this->badge(AdvisoryLevel::Info, "{$report->subject()} complete — {$body}.");
    }

    protected function sentences(CommandReport $report): string
    {
        $parts = [];

        foreach ($report->visibleCounts() as $value => $count) {
            $parts[] = ReconciliationAction::from($value)->sentence($report->tense(), $count);
        }

        if ($parts === []) {
            return strtolower(rtrim($this->emptyState($report), '.'));
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        $last = array_pop($parts);

        return $report->dryRun()
            ? implode(', ', $parts).' and '.$last
            : implode(', ', $parts).', '.$last;
    }

    protected function emptyState(CommandReport $report): string
    {
        return $report->inventoryIsEmpty() ? 'No assets found.' : 'No action needed.';
    }

    protected function tokenColor(ReportRecord $record): string
    {
        return $record->status->isFailed() ? 'red' : $record->action->color();
    }

    protected function badge(AdvisoryLevel $level, string $message): void
    {
        $message = $this->escape($message);

        match ($level) {
            AdvisoryLevel::Info => $this->components->info($message),
            AdvisoryLevel::Warn => $this->components->warn($message),
            AdvisoryLevel::Error => $this->components->error($message),
        };
    }

    protected function escape(string $value): string
    {
        return OutputFormatter::escape($value);
    }

    protected function heading(string $label, ?string $suffix = null): void
    {
        $this->output->newLine();
        $heading = "  <options=bold>{$label}</>";

        if ($suffix !== null) {
            $heading .= "  <fg=yellow;options=bold>{$suffix}</>";
        }

        $this->output->writeln($heading);
        $this->output->newLine();
    }
}

<?php

namespace Daun\StatamicMux\Console;

use Daun\StatamicMux\Mux\Enums\ReconciliationAction;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Formatter\OutputFormatter;

final class CommandRenderer
{
    public function __construct(
        protected OutputStyle $output,
    ) {}

    public function render(CommandReport $report): void
    {
        if ($this->output->isQuiet()) {
            return;
        }

        $this->output->newLine();

        if ($report->dryRun()) {
            $this->callout('yellow', '<fg=yellow;options=bold>Dry run</> <fg=gray>— no changes will be made</>');
            $this->output->newLine();
        }

        $this->context($report);
        $this->advisories($report);
        $this->plan($report);
        $this->details($report);
        $this->failures($report);
        $this->summary($report);
        $this->output->newLine();
    }

    /**
     * Inventory commands summarize their scope in one dim line at -v; commands
     * without an inventory (mux:debug) carry their content in context rows.
     */
    protected function context(CommandReport $report): void
    {
        if ($report->hasInventory()) {
            if (! $this->output->isVerbose() || ($context = $this->contextSummary($report)) === '') {
                return;
            }

            $this->output->writeln('  <fg=gray>'.$this->escape($context).'</>');
            $this->output->newLine();

            return;
        }

        $rows = $report->contextRows();

        if ($rows === []) {
            return;
        }

        $width = max(array_map(fn ($row) => mb_strlen($row['label']), $rows));

        foreach ($rows as $row) {
            $label = $this->escape(str_pad($row['label'], $width));
            $value = $row['value'] === null ? '' : '  <options=bold>'.$this->escape($row['value']).'</>';

            $this->output->writeln("  {$label}{$value}");
        }

        $this->output->newLine();
    }

    protected function contextSummary(CommandReport $report): string
    {
        $parts = [];

        if ($containers = $report->containers()) {
            $parts[] = 'container: '.implode(', ', $containers);
        }

        if (($locals = $report->locals()) !== null) {
            $parts[] = "{$locals} local";
        }

        if (($remotes = $report->remotes()) !== null) {
            $parts[] = "{$remotes} on Mux";
        }

        foreach ($report->contextRows() as $row) {
            $parts[] = strtolower($row['label']).': '.($row['value'] ?? '—');
        }

        return implode(' · ', $parts);
    }

    protected function advisories(CommandReport $report): void
    {
        foreach ($report->advisories() as $advisory) {
            $color = $this->levelColor($advisory->level);

            $this->callout($color, $this->escape($advisory->message));

            foreach ($advisory->records as $record) {
                $this->callout($color, '<fg=gray>'.$this->escape($record).'</>');
            }

            if ($advisory->hint) {
                $this->callout($color, '<fg=gray>'.$this->escape($advisory->hint).'</>');
            }

            $this->output->newLine();
        }
    }

    protected function plan(CommandReport $report): void
    {
        if (! $report->hasPlan()) {
            return;
        }

        $rows = [];

        foreach ($report->visibleCounts() as $value => $count) {
            $action = ReconciliationAction::from($value);

            $rows[] = ['action' => $action, 'count' => $count, 'breakdown' => $report->reasonBreakdown($action)];
        }

        if ($rows === []) {
            $this->output->writeln('  <fg=gray>'.$this->emptyState($report).'</>');
            $this->output->newLine();

            return;
        }

        $verbWidth = max(array_map(fn ($row) => mb_strlen($row['action']->label()), $rows));
        $countWidth = max(array_map(
            fn ($row) => max([strlen((string) $row['count']), ...array_map(strlen(...), array_map(strval(...), $row['breakdown']))]),
            $rows,
        ));

        foreach ($rows as $row) {
            $verb = sprintf('<fg=%s;options=bold>%s</>', $this->verbColor($row['action']), $this->escape($row['action']->label()));
            $pad = str_repeat(' ', $verbWidth - mb_strlen($row['action']->label()));
            $count = sprintf('<options=bold>%'.$countWidth.'s</>', $row['count']);

            if (count($row['breakdown']) > 1) {
                $this->output->writeln("  {$verb}{$pad}  {$count}");

                foreach ($row['breakdown'] as $reason => $reasonCount) {
                    $indent = str_repeat(' ', $verbWidth);
                    $sub = sprintf('%'.$countWidth.'s', $reasonCount);

                    $this->output->writeln("  <fg=gray>{$indent}  {$sub}  ".$this->escape($reason).'</>');
                }

                continue;
            }

            $reason = (string) array_key_first($row['breakdown']);
            $suffix = $reason !== '' ? '  <fg=gray>'.$this->escape($reason).'</>' : '';

            $this->output->writeln("  {$verb}{$pad}  {$count}{$suffix}");
        }

        $this->output->newLine();
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

            $rendered = true;

            $this->output->writeln(sprintf('  <fg=%s;options=bold>%s</>', $this->verbColor($action), $this->escape($action->label())));

            foreach ($records as $record) {
                $reason = $record->reason();
                $line = '    '.$this->escape($record->display($full));

                if ($reason) {
                    $line .= '  <fg=gray>'.$this->escape($reason).'</>';
                }

                $line .= sprintf('  <fg=%s>%s</>', $this->tokenColor($record), $record->token($report->tense()));

                $this->output->writeln($line);

                if ($record->error) {
                    $this->output->writeln('      <fg=red>'.$this->escape($record->error).'</>');
                }

                if ($full) {
                    foreach ($record->diagnostics as $label => $value) {
                        $this->output->writeln('      <fg=gray>'.$this->escape($label).': '.$this->escape($value).'</>');
                    }
                }
            }
        }

        if ($rendered) {
            $this->output->newLine();
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

        foreach ($failures as $failure) {
            $this->callout('red', '<options=bold>'.$this->escape($failure->id).'</>  <fg=gray>'.$this->escape($failure->error).'</>');
        }

        $this->output->newLine();
    }

    protected function summary(CommandReport $report): void
    {
        if ($override = $report->summaryOverride()) {
            $this->summaryLine($this->summaryColor($override['level']), $override['message']);

            return;
        }

        // An empty plan already printed its own line — a summary would repeat it.
        if ($report->hasPlan() && ! $report->hasVisibleActions() && $report->failures() === []) {
            return;
        }

        $body = $this->sentences($report);
        $failures = count($report->failures());

        if ($report->dryRun()) {
            $message = $report->hasVisibleActions()
                ? "{$body} pending."
                : "{$report->subject()} — {$body}.";

            $this->summaryLine($failures ? 'yellow' : 'cyan', $message);

            return;
        }

        if ($failures) {
            $noun = $failures === 1 ? 'failure' : 'failures';
            $this->summaryLine('yellow', "{$report->subject()} finished with {$failures} {$noun} — {$body}.");

            return;
        }

        $this->summaryLine('green', "{$report->subject()} complete — {$body}.");
    }

    protected function summaryLine(string $color, string $message): void
    {
        $this->output->writeln("  <fg={$color}>●</> <options=bold>".$this->escape($message).'</>');
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

    /** Gray actions render as plain text in the plan and detail lists. */
    protected function verbColor(ReconciliationAction $action): string
    {
        $color = $action->color();

        return $color === 'gray' ? 'default' : $color;
    }

    protected function tokenColor(ReportRecord $record): string
    {
        return $record->status->isFailed() ? 'red' : $record->action->color();
    }

    protected function summaryColor(AdvisoryLevel $level): string
    {
        return match ($level) {
            AdvisoryLevel::Info => 'green',
            AdvisoryLevel::Warn => 'yellow',
            AdvisoryLevel::Error => 'red',
        };
    }

    protected function levelColor(AdvisoryLevel $level): string
    {
        return match ($level) {
            AdvisoryLevel::Info => 'blue',
            AdvisoryLevel::Warn => 'yellow',
            AdvisoryLevel::Error => 'red',
        };
    }

    protected function callout(string $color, string $line): void
    {
        $this->output->writeln("  <fg={$color}>▎</> {$line}");
    }

    protected function escape(string $value): string
    {
        return OutputFormatter::escape($value);
    }
}

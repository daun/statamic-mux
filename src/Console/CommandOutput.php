<?php

namespace Daun\StatamicMux\Console;

use Daun\StatamicMux\Support\Queue;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;

final class CommandOutput
{
    public function __construct(
        protected Command $command,
    ) {}

    public static function for(Command $command): self
    {
        return new self($command);
    }

    public function tense(bool $dryRun): Tense
    {
        return Tense::for($dryRun, Queue::isSync());
    }

    public function report(bool $dryRun): CommandReport
    {
        return new CommandReport($this->command->getName() ?? '', $this->tense($dryRun));
    }

    public function wantsJson(): bool
    {
        return $this->command->getDefinition()->hasOption('json')
            && (bool) $this->command->option('json');
    }

    public function finish(CommandReport $report): int
    {
        if ($this->wantsJson()) {
            // Quiet verbosity still prints: the JSON object is the entire contract.
            $this->command->getOutput()->getOutput()->writeln($report->toJson(), OutputInterface::VERBOSITY_QUIET);
        } else {
            (new CommandRenderer($this->command->getOutput()))->render($report);
        }

        return $report->exitCode();
    }
}

<?php

use Daun\StatamicMux\Console\CommandOutput;
use Daun\StatamicMux\Console\CommandReport;
use Daun\StatamicMux\Console\ReportFailure;
use Daun\StatamicMux\Console\ReportRecord;
use Daun\StatamicMux\Mux\Enums\ReconciliationAction;
use Daun\StatamicMux\Mux\Enums\ReconciliationState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ReportingTestCommand extends Command
{
    protected $signature = 'mux:reporting-test {--dry-run} {--json} {--fail}';

    public function handle(): int
    {
        $output = CommandOutput::for($this);

        $report = $output->report((bool) $this->option('dry-run'))
            ->scope(['assets'], locals: 1, remotes: 0)
            ->record(ReportRecord::make(ReconciliationAction::Upload, 'assets::trailer.mp4', ReconciliationState::Upload));

        if ($this->option('fail')) {
            $report->failure(ReportFailure::make('Boom', ReconciliationAction::Upload, 'assets::trailer.mp4'));
        }

        return $output->finish($report);
    }
}

beforeEach(function () {
    Artisan::registerCommand(new ReportingTestCommand);
});

test('emits a single json object and no human output', function () {
    $code = Artisan::call('mux:reporting-test', ['--json' => true, '--dry-run' => true]);
    $output = Artisan::output();

    expect($code)->toBe(CommandReport::SUCCESS);
    expect(json_decode(trim($output), true))->toMatchArray([
        'command' => 'mux:reporting-test',
        'dry_run' => true,
        'tense' => 'planned',
        'scope' => ['containers' => ['assets'], 'locals' => 1, 'remotes' => 0],
        'exit_code' => 0,
    ]);
    expect($output)->not->toContain('Plan');
});

test('returns the report exit code', function () {
    $code = Artisan::call('mux:reporting-test', ['--json' => true, '--fail' => true]);

    expect($code)->toBe(CommandReport::FAILURE);
    expect(json_decode(trim(Artisan::output()), true)['failures'])->toBe([
        ['action' => 'upload', 'id' => 'assets::trailer.mp4', 'error' => 'Boom'],
    ]);
});

test('renders human output without the json flag', function () {
    Artisan::call('mux:reporting-test', ['--dry-run' => true]);

    expect(Artisan::output())
        ->toContain('Plan')
        ->toContain('upload')
        ->not->toContain('"command"');
});

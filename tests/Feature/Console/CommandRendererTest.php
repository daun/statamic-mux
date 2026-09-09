<?php

use Daun\StatamicMux\Console\Advisory;
use Daun\StatamicMux\Console\CommandRenderer;
use Daun\StatamicMux\Console\CommandReport;
use Daun\StatamicMux\Console\ReportFailure;
use Daun\StatamicMux\Console\ReportRecord;
use Daun\StatamicMux\Console\Tense;
use Daun\StatamicMux\Mux\Enums\ReconciliationAction;
use Daun\StatamicMux\Mux\Enums\ReconciliationState;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

function renderReportRaw(CommandReport $report, int $verbosity = OutputInterface::VERBOSITY_NORMAL): string
{
    $buffer = new BufferedOutput($verbosity, false);
    $style = new OutputStyle(new ArrayInput([]), $buffer);

    (new CommandRenderer($style))->render($report);

    return $buffer->fetch();
}

function renderReport(CommandReport $report, int $verbosity = OutputInterface::VERBOSITY_NORMAL): string
{
    // Collapse dot leaders, padding and component line wrapping into single spaces.
    return trim((string) preg_replace(['/\.{2,}/', '/\s+/'], ' ', renderReportRaw($report, $verbosity)));
}

function mirrorReport(Tense $tense = Tense::Planned): CommandReport
{
    return (new CommandReport('mux:mirror', $tense))
        ->scope(['assets'], locals: 11, remotes: 453)
        ->context('Queue', 'redis (background)')
        ->record(ReportRecord::make(ReconciliationAction::Upload, 'assets::trailer.mp4', ReconciliationState::Upload))
        ->count(ReconciliationAction::Prune, 442, 'superseded by a newer upload')
        ->count(ReconciliationAction::Skip, 7);
}

test('renders the context block', function () {
    $output = renderReport(mirrorReport());

    expect($output)->toContain('mux:mirror');
    expect($output)->toContain('Plan DRY RUN');
    expect(substr_count($output, 'DRY RUN'))->toBe(1);
    expect($output)->toContain('Containers assets');
    expect($output)->toContain('Local videos 11');
    expect($output)->toContain('Mux assets 453');
    expect($output)->toContain('Queue redis (background)');
});

test('renders only visible plan rows with a count of one or more', function () {
    $output = renderReport(mirrorReport());

    expect($output)->toContain('Plan DRY RUN');
    expect($output)->toContain('upload local videos not yet on Mux 1');
    expect($output)->toContain('prune superseded by a newer upload 442');
    expect($output)->not->toContain('keep');
    expect($output)->not->toContain('skip');
});

test('renders multiple reasons as indented plan rows', function () {
    $report = (new CommandReport('mux:prune', Tense::Applied))
        ->scope([], locals: 1, remotes: 3)
        ->record(ReportRecord::make(ReconciliationAction::Prune, 'one', ReconciliationState::MissingSource))
        ->record(ReportRecord::make(ReconciliationAction::Prune, 'two', ReconciliationState::ExpiredProxy))
        ->record(ReportRecord::make(ReconciliationAction::Prune, 'three', ReconciliationState::ExpiredProxy));

    $output = renderReport($report);
    $raw = renderReportRaw($report);

    expect($output)
        ->toContain('prune 3')
        ->toContain('local asset no longer exists 1')
        ->toContain('expired placeholder clip 2')
        ->not->toContain('local asset no longer exists (1)');

    expect($raw)
        ->toMatch('/^  \x{00A0}{10}local asset no longer exists/mu')
        ->toMatch('/^  \x{00A0}{10}expired placeholder clip/mu');
});

test('never renders per-record lines at normal verbosity', function () {
    expect(renderReport(mirrorReport()))->not->toContain('assets::trailer.mp4');
});

test('renders the detail block at -v only', function () {
    $verbose = renderReport(mirrorReport(), OutputInterface::VERBOSITY_VERBOSE);

    expect($verbose)->toContain('Detail');
    expect($verbose)->toContain('assets::trailer.mp4 local videos not yet on Mux UPLOAD');
});

test('renders full ids and diagnostics at -vv', function () {
    $report = (new CommandReport('mux:prune', Tense::Applied))
        ->scope([], locals: 0, remotes: 1)
        ->record(ReportRecord::make(
            ReconciliationAction::Prune,
            'QIxLONGMUXIDVALUE',
            ReconciliationState::Superseded,
            label: 'QIx…',
            diagnostics: ['resolution' => '1080p'],
        ));

    expect(renderReport($report, OutputInterface::VERBOSITY_VERBOSE))
        ->toContain('QIx… superseded by a newer upload PRUNED')
        ->not->toContain('resolution: 1080p');

    expect(renderReport($report, OutputInterface::VERBOSITY_VERY_VERBOSE))
        ->toContain('QIxLONGMUXIDVALUE')
        ->toContain('resolution: 1080p');
});

test('never renders skipped records in human output', function () {
    $report = (new CommandReport('mux:mirror', Tense::Applied))
        ->scope([], locals: 1, remotes: 1)
        ->record(ReportRecord::make(ReconciliationAction::Skip, 'assets::inflight.mp4', ReconciliationState::ProxyInFlight));

    foreach ([OutputInterface::VERBOSITY_NORMAL, OutputInterface::VERBOSITY_VERBOSE, OutputInterface::VERBOSITY_VERY_VERBOSE] as $verbosity) {
        $output = renderReport($report, $verbosity);

        expect($output)->not->toContain('assets::inflight.mp4');
        expect($output)->not->toContain('SKIPPED');
    }
});

test('renders nothing when quiet', function () {
    expect(renderReport(mirrorReport(), OutputInterface::VERBOSITY_QUIET))->toBe('');
});

test('renders advisories in full with their records and hint', function () {
    $report = mirrorReport()->advisory(Advisory::warn(
        'proxy-source-conflict',
        '2 local placeholder clips have a full Mux encoding.',
        ['assets::trailer.mp4', 'assets::teaser.mp4'],
        'Re-link them first: php artisan mux:relink',
    ));

    $output = renderReport($report);

    expect($output)->toContain('2 local placeholder clips have a full Mux encoding.');
    expect($output)->toContain('assets::trailer.mp4');
    expect($output)->toContain('assets::teaser.mp4');
    expect($output)->toContain('Re-link them first: php artisan mux:relink');
});

test('escapes dynamic console markup', function () {
    $report = (new CommandReport('mux:upload', Tense::Applied))
        ->scope(['<error>videos</error>'], locals: 1, remotes: 0)
        ->record(ReportRecord::make(
            ReconciliationAction::Upload,
            '<error>trailer.mp4</error>',
            ReconciliationState::Upload,
        ))
        ->advisory(Advisory::warn('markup', '<error>warning</error>', ['<error>record</error>']));

    $output = renderReport($report, OutputInterface::VERBOSITY_VERBOSE);

    expect($output)
        ->toContain('<error>videos</error>')
        ->toContain('<error>trailer.mp4</error>')
        ->toContain('<error>warning</error>')
        ->toContain('<error>record</error>');
});

test('renders a dry run summary without repeating the run mode', function () {
    expect(renderReport(mirrorReport()))
        ->toContain('1 upload and 442 prunes pending.')
        ->not->toContain('Dry run —')
        ->not->toContain('Nothing was changed.');
});

test('renders a completed summary in command-aware grammar', function () {
    $report = (new CommandReport('mux:mirror', Tense::Applied))
        ->scope([], locals: 3, remotes: 5)
        ->count(ReconciliationAction::Upload, 3)
        ->count(ReconciliationAction::Prune, 442)
        ->count(ReconciliationAction::Keep, 8);

    expect(renderReport($report))->toContain('Mirror complete — 3 uploaded, 442 pruned, 8 kept.');
});

test('renders a failure summary and the failure list', function () {
    $report = (new CommandReport('mux:upload', Tense::Applied))
        ->scope([], locals: 3, remotes: 0)
        ->count(ReconciliationAction::Upload, 1)
        ->failure(ReportFailure::make('Boom', ReconciliationAction::Upload, 'assets::trailer.mp4'));

    $output = renderReport($report);

    expect($output)->toContain('assets::trailer.mp4: Boom');
    expect($output)->toContain('Upload finished with 1 failure — 1 uploaded.');
});

test('renders an aborted summary override', function () {
    $report = (new CommandReport('mux:prune', Tense::Applied))
        ->scope([], locals: 1, remotes: 1)
        ->abort('Prune aborted — 2 assets could not be re-linked.');

    expect(renderReport($report))->toContain('Prune aborted — 2 assets could not be re-linked.');
});

test('distinguishes an empty inventory from an idle plan', function () {
    $empty = (new CommandReport('mux:prune', Tense::Applied))->scope([], locals: 0, remotes: 0);
    $idle = (new CommandReport('mux:prune', Tense::Applied))->scope([], locals: 4, remotes: 9);

    expect(renderReport($empty))
        ->toContain('No assets found.')
        ->toContain('Prune complete — no assets found.');

    expect(renderReport($idle))
        ->toContain('No action needed.')
        ->toContain('Prune complete — no action needed.');
});

test('omits the plan block for commands without an inventory', function () {
    $report = (new CommandReport('mux:debug', Tense::Applied))
        ->context('Credentials', 'OK')
        ->context('Queue', 'SYNC (not recommended)');

    $output = renderReport($report);

    expect($output)->toContain('Credentials OK');
    expect($output)->toContain('Queue SYNC (not recommended)');
    expect($output)->not->toContain('Plan');
});

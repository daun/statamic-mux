<?php

use Daun\StatamicMux\Console\Advisory;
use Daun\StatamicMux\Console\CommandReport;
use Daun\StatamicMux\Console\ReportFailure;
use Daun\StatamicMux\Console\ReportRecord;
use Daun\StatamicMux\Console\Tense;
use Daun\StatamicMux\Mux\Enums\ReconciliationAction;
use Daun\StatamicMux\Mux\Enums\ReconciliationState;

test('serializes the json contract', function () {
    $report = (new CommandReport('mux:mirror', Tense::Planned))
        ->scope(['assets'], locals: 11, remotes: 453)
        ->context('Queue', 'redis (background)')
        ->record(ReportRecord::make(ReconciliationAction::Upload, 'assets::trailer.mp4', ReconciliationState::Unlinked))
        ->count(ReconciliationAction::Prune, 442)
        ->count(ReconciliationAction::Ignore, 3)
        ->count(ReconciliationAction::Skip, 2)
        ->advisory(Advisory::warn('proxy-source-conflict', 'Placeholder clips have a full encoding.', ['assets::trailer.mp4']));

    expect($report->toArray())->toBe([
        'command' => 'mux:mirror',
        'dry_run' => true,
        'tense' => 'planned',
        'scope' => ['containers' => ['assets'], 'locals' => 11, 'remotes' => 453],
        'context' => ['Queue' => 'redis (background)'],
        'plan' => [
            'relink' => 0,
            'upload' => 1,
            're-upload' => 0,
            'prune' => 442,
            'keep' => 0,
            'hold' => 0,
            'ignore' => 3,
            'skip' => 2,
        ],
        'records' => [
            ['action' => 'upload', 'id' => 'assets::trailer.mp4', 'state' => 'unlinked', 'reason' => null],
        ],
        'advisories' => [
            ['level' => 'warn', 'code' => 'proxy-source-conflict', 'records' => ['assets::trailer.mp4']],
        ],
        'failures' => [],
        'exit_code' => 0,
    ]);

    expect(json_decode($report->toJson(), true))->toBe($report->toArray());
});

test('counts records and bulk counts together', function () {
    $report = (new CommandReport('mux:prune', Tense::Applied))
        ->record(ReportRecord::make(ReconciliationAction::Prune, 'abc', ReconciliationState::Superseded))
        ->count(ReconciliationAction::Prune, 2);

    expect($report->countOf(ReconciliationAction::Prune))->toBe(3);
    expect($report->countOf(ReconciliationAction::Keep))->toBe(0);
});

test('ignores non-positive bulk counts', function () {
    $report = (new CommandReport('mux:prune'))->count(ReconciliationAction::Keep, 0);

    expect($report->hasVisibleActions())->toBeFalse();
    expect($report->counts()['keep'])->toBe(0);
});

test('hides zero counts and the internal skip action from visible counts', function () {
    $report = (new CommandReport('mux:mirror'))
        ->count(ReconciliationAction::Skip, 4)
        ->count(ReconciliationAction::Upload, 2);

    expect($report->visibleCounts())->toBe(['upload' => 2]);
    expect($report->counts()['skip'])->toBe(4);
});

test('treats a skip-only plan as having no visible actions', function () {
    $report = (new CommandReport('mux:mirror'))
        ->scope([], locals: 3, remotes: 4)
        ->count(ReconciliationAction::Skip, 3);

    expect($report->hasVisibleActions())->toBeFalse();
    expect($report->inventoryIsEmpty())->toBeFalse();
});

test('distinguishes an empty inventory from an empty plan', function () {
    $empty = (new CommandReport('mux:prune'))->scope([], locals: 0, remotes: 0);
    $idle = (new CommandReport('mux:prune'))->scope([], locals: 3, remotes: 3);
    $unscoped = new CommandReport('mux:debug');

    expect($empty->inventoryIsEmpty())->toBeTrue();
    expect($idle->inventoryIsEmpty())->toBeFalse();
    expect($unscoped->inventoryIsEmpty())->toBeFalse();
    expect($unscoped->hasInventory())->toBeFalse();
    expect($unscoped->hasPlan())->toBeFalse();
    expect($empty->hasPlan())->toBeTrue();
});

test('groups reasons behind an action', function () {
    $report = (new CommandReport('mux:prune'))
        ->record(ReportRecord::make(ReconciliationAction::Prune, 'a', ReconciliationState::Superseded))
        ->record(ReportRecord::make(ReconciliationAction::Prune, 'b', ReconciliationState::Superseded))
        ->record(ReportRecord::make(ReconciliationAction::Prune, 'c', ReconciliationState::MissingSource));

    expect($report->reasonBreakdown(ReconciliationAction::Prune))->toBe([
        'superseded by a newer upload' => 2,
        'local asset no longer exists' => 1,
    ]);
});

test('records failures and fails the exit code', function () {
    $report = (new CommandReport('mux:upload', Tense::Applied))
        ->failure(ReportFailure::make('Boom', ReconciliationAction::Upload, 'assets::trailer.mp4'));

    expect($report->exitCode())->toBe(CommandReport::FAILURE);
    expect($report->failed())->toBeTrue();
    expect($report->toArray()['failures'])->toBe([
        ['action' => 'upload', 'id' => 'assets::trailer.mp4', 'error' => 'Boom'],
    ]);
});

test('emits one json object for handled precondition failures', function () {
    $report = (new CommandReport('mux:mirror'))
        ->failure(ReportFailure::make('Mux is not configured.'));

    $json = json_decode($report->toJson(), true);

    expect($json['failures'])->toBe([['action' => null, 'id' => null, 'error' => 'Mux is not configured.']]);
    expect($json['exit_code'])->toBe(1);
    expect($json['records'])->toBe([]);
});

test('aborting overrides the summary and fails', function () {
    $report = (new CommandReport('mux:prune', Tense::Applied))->abort('Prune aborted.');

    expect($report->summaryOverride()['message'])->toBe('Prune aborted.');
    expect($report->exitCode())->toBe(CommandReport::FAILURE);
});

test('retains command names and derives summary subjects', function () {
    expect((new CommandReport('mux:mirror'))->command())->toBe('mux:mirror');
    expect((new CommandReport('mux:mirror'))->subject())->toBe('Mirror');
    expect((new CommandReport('statamic:mux:prune'))->subject())->toBe('Prune');
});

test('tracks record status transitions', function () {
    $record = ReportRecord::make(ReconciliationAction::Upload, 'assets::trailer.mp4', ReconciliationState::Upload);

    expect($record->token(Tense::Planned))->toBe('UPLOAD');
    expect($record->succeeded()->token(Tense::Applied))->toBe('UPLOADED');
    expect($record->failed('Boom')->token(Tense::Applied))->toBe('FAILED');
    expect($record->failed('Boom')->error)->toBe('Boom');
    expect($record->reason())->toBe('local videos not yet on Mux');
    expect($record->display())->toBe('assets::trailer.mp4');
    expect(ReportRecord::make(ReconciliationAction::Prune, 'QIxLONGID', label: 'QIx…')->display())->toBe('QIx…');
    expect(ReportRecord::make(ReconciliationAction::Prune, 'QIxLONGID', label: 'QIx…')->display(full: true))->toBe('QIxLONGID');
});

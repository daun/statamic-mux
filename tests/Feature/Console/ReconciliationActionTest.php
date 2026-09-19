<?php

use Daun\StatamicMux\Console\Tense;
use Daun\StatamicMux\Mux\Enums\ReconciliationAction;

test('covers the eight shared verbs', function () {
    expect(array_map(fn (ReconciliationAction $action) => $action->value, ReconciliationAction::cases()))
        ->toBe(['relink', 'upload', 're-upload', 'prune', 'keep', 'hold', 'ignore', 'skip']);
});

test('orders actions for plan rows and summaries', function () {
    expect(array_map(fn (ReconciliationAction $action) => $action->value, ReconciliationAction::ordered()))
        ->toBe(['relink', 'upload', 're-upload', 'prune', 'keep', 'hold', 'ignore', 'skip']);
});

test('hides the internal skip action from rendered output', function () {
    expect(ReconciliationAction::Skip->isInternal())->toBeTrue();
    expect(ReconciliationAction::Skip->isVisible())->toBeFalse();

    foreach (ReconciliationAction::visible() as $action) {
        expect($action)->not->toBe(ReconciliationAction::Skip);
    }

    expect(ReconciliationAction::visible())->toHaveCount(7);
});

test('renders tense-aware status tokens', function () {
    expect(ReconciliationAction::Upload->token(Tense::Planned))->toBe('UPLOAD');
    expect(ReconciliationAction::Upload->token(Tense::Queued))->toBe('QUEUED');
    expect(ReconciliationAction::Upload->token(Tense::Applied))->toBe('UPLOADED');

    expect(ReconciliationAction::Prune->token(Tense::Planned))->toBe('PRUNE');
    expect(ReconciliationAction::Prune->token(Tense::Applied))->toBe('PRUNED');

    expect(ReconciliationAction::Reupload->token(Tense::Applied))->toBe('RE-UPLOADED');
});

test('keeps tense-independent tokens stable', function () {
    foreach (Tense::cases() as $tense) {
        expect(ReconciliationAction::Hold->token($tense))->toBe('HELD');
        expect(ReconciliationAction::Ignore->token($tense))->toBe('IGNORED');
        expect(ReconciliationAction::Skip->token($tense))->toBe('SKIPPED');
    }
});

test('renders tense-aware summary sentences', function () {
    expect(ReconciliationAction::Upload->sentence(Tense::Planned, 3))->toBe('3 uploads');
    expect(ReconciliationAction::Upload->sentence(Tense::Queued, 3))->toBe('3 queued for upload');
    expect(ReconciliationAction::Upload->sentence(Tense::Applied, 3))->toBe('3 uploaded');

    expect(ReconciliationAction::Prune->sentence(Tense::Planned, 442))->toBe('442 prunes');
    expect(ReconciliationAction::Prune->sentence(Tense::Queued, 442))->toBe('442 queued for removal');
    expect(ReconciliationAction::Prune->sentence(Tense::Applied, 442))->toBe('442 pruned');

    expect(ReconciliationAction::Keep->sentence(Tense::Applied, 8))->toBe('8 kept');
    expect(ReconciliationAction::Hold->sentence(Tense::Planned, 2))->toBe('2 held');
});

test('pluralizes planned sentences', function () {
    expect(ReconciliationAction::Upload->sentence(Tense::Planned, 1))->toBe('1 upload');
    expect(ReconciliationAction::Prune->sentence(Tense::Planned, 1))->toBe('1 prune');
    expect(ReconciliationAction::Relink->sentence(Tense::Planned, 1))->toBe('1 re-link');
    expect(ReconciliationAction::Reupload->sentence(Tense::Planned, 2))->toBe('2 re-uploads');
});

test('exposes labels and colors', function () {
    expect(ReconciliationAction::Reupload->label())->toBe('re-upload');
    expect(ReconciliationAction::Upload->color())->toBe('green');
    expect(ReconciliationAction::Prune->color())->toBe('red');
    expect(ReconciliationAction::Hold->color())->toBe('yellow');
    expect(ReconciliationAction::Keep->color())->toBe('gray');
});

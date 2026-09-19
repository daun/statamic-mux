<?php

use Daun\StatamicMux\Mux\Enums\ReconciliationAction;
use Daun\StatamicMux\Mux\Enums\ReconciliationState;
use Daun\StatamicMux\Mux\Enums\Side;

test('maps local states to actions', function (ReconciliationState $state, ReconciliationAction $action) {
    expect($state->action(Side::Local))->toBe($action);
})->with([
    [ReconciliationState::Linked, ReconciliationAction::Keep],
    [ReconciliationState::Upload, ReconciliationAction::Upload],
    [ReconciliationState::Reupload, ReconciliationAction::Reupload],
    [ReconciliationState::Unlinked, ReconciliationAction::Relink],
    [ReconciliationState::ProxySource, ReconciliationAction::Relink],
    [ReconciliationState::MediaMismatch, ReconciliationAction::Hold],
    [ReconciliationState::NonReadyLinked, ReconciliationAction::Hold],
    [ReconciliationState::UnknownStatus, ReconciliationAction::Hold],
    [ReconciliationState::Preparing, ReconciliationAction::Skip],
]);

test('maps remote states to actions', function (ReconciliationState $state, ReconciliationAction $action) {
    expect($state->action(Side::Remote))->toBe($action);
})->with([
    [ReconciliationState::Linked, ReconciliationAction::Keep],
    [ReconciliationState::Superseded, ReconciliationAction::Prune],
    [ReconciliationState::MissingSource, ReconciliationAction::Prune],
    [ReconciliationState::Unlinked, ReconciliationAction::Prune],
    [ReconciliationState::ProxySource, ReconciliationAction::Prune],
    [ReconciliationState::Errored, ReconciliationAction::Prune],
    [ReconciliationState::UnmanagedSource, ReconciliationAction::Prune],
    [ReconciliationState::ExpiredProxy, ReconciliationAction::Prune],
    [ReconciliationState::OrphanedProxy, ReconciliationAction::Prune],
    [ReconciliationState::SharedReference, ReconciliationAction::Hold],
    [ReconciliationState::AttributionConflict, ReconciliationAction::Hold],
    [ReconciliationState::MediaMismatch, ReconciliationAction::Hold],
    [ReconciliationState::UnknownStatus, ReconciliationAction::Hold],
    [ReconciliationState::Foreign, ReconciliationAction::Ignore],
    [ReconciliationState::Unattributable, ReconciliationAction::Ignore],
    [ReconciliationState::Preparing, ReconciliationAction::Skip],
    [ReconciliationState::ProxyInFlight, ReconciliationAction::Skip],
]);

test('reads the same state differently per side', function () {
    expect(ReconciliationState::ProxySource->action(Side::Local))->toBe(ReconciliationAction::Relink);
    expect(ReconciliationState::ProxySource->action(Side::Remote))->toBe(ReconciliationAction::Prune);
    expect(ReconciliationState::Unlinked->action(Side::Local))->toBe(ReconciliationAction::Relink);
    expect(ReconciliationState::Unlinked->action(Side::Remote))->toBe(ReconciliationAction::Prune);
});

test('rejects impossible state and side combinations', function () {
    expect(fn () => ReconciliationState::Superseded->action(Side::Local))
        ->toThrow(LogicException::class, 'cannot occur on the local side');

    expect(fn () => ReconciliationState::Upload->action(Side::Remote))
        ->toThrow(LogicException::class, 'cannot occur on the remote side');
});

test('gives every state a reason', function () {
    foreach (ReconciliationState::cases() as $state) {
        expect($state->reason())->toBeString()->not->toBeEmpty();
    }

    expect(ReconciliationState::MissingSource->reason())->toBe('local asset no longer exists');
    expect(ReconciliationState::Superseded->reason())->toBe('superseded by a newer upload');
    expect(ReconciliationState::Foreign->reason())->toBe('not created by this addon');
});

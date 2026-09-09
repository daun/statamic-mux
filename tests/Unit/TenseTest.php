<?php

use Daun\StatamicMux\Console\Tense;

test('derives the tense from the run mode', function () {
    expect(Tense::for(dryRun: true, sync: false))->toBe(Tense::Planned);
    expect(Tense::for(dryRun: true, sync: true))->toBe(Tense::Planned);
    expect(Tense::for(dryRun: false, sync: true))->toBe(Tense::Applied);
    expect(Tense::for(dryRun: false, sync: false))->toBe(Tense::Queued);
});

test('exposes serializable values', function () {
    expect(array_map(fn (Tense $tense) => $tense->value, Tense::cases()))
        ->toBe(['planned', 'queued', 'applied']);
});

test('answers which tense it is', function () {
    expect(Tense::Planned->isPlanned())->toBeTrue();
    expect(Tense::Planned->isQueued())->toBeFalse();
    expect(Tense::Queued->isQueued())->toBeTrue();
    expect(Tense::Applied->isApplied())->toBeTrue();
    expect(Tense::Applied->isPlanned())->toBeFalse();
});

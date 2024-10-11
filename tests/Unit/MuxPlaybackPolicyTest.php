<?php

use Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy;
use Illuminate\Support\Collection;

test('returns possible values', function () {
    expect(MuxPlaybackPolicy::values())->toEqual(['public', 'signed']);
});

test('makes values from strings', function () {
    expect(MuxPlaybackPolicy::make('public'))->toEqual(MuxPlaybackPolicy::Public);
    expect(MuxPlaybackPolicy::make('signed'))->toEqual(MuxPlaybackPolicy::Signed);
});

test('ignores invalid values', function () {
    expect(MuxPlaybackPolicy::make(null))->toBeNull();
    expect(MuxPlaybackPolicy::make('loremipsum'))->toBeNull();
});

test('makes values from enums', function () {
    expect(MuxPlaybackPolicy::make(MuxPlaybackPolicy::Public))->toEqual(MuxPlaybackPolicy::Public);
    expect(MuxPlaybackPolicy::make(MuxPlaybackPolicy::Signed))->toEqual(MuxPlaybackPolicy::Signed);
});

test('makes many values', function () {
    expect(MuxPlaybackPolicy::makeMany([]))->toBeInstanceOf(Collection::class)->toHaveCount(0);
});

test('makes many values from array', function () {
    expect(MuxPlaybackPolicy::makeMany(['public', null, 'signed', MuxPlaybackPolicy::Signed])->all())
        ->toEqual([MuxPlaybackPolicy::Public, MuxPlaybackPolicy::Signed]);
});

test('makes many values from string', function () {
    expect(MuxPlaybackPolicy::makeMany(' public, signed,loremipsum,signed ')->all())
        ->toEqual([MuxPlaybackPolicy::Public, MuxPlaybackPolicy::Signed]);
});

test('checks if public or signed', function () {
    expect(MuxPlaybackPolicy::Public->isPublic())->toBeTrue();
    expect(MuxPlaybackPolicy::Public->isSigned())->toBeFalse();
    expect(MuxPlaybackPolicy::Signed->isPublic())->toBeFalse();
    expect(MuxPlaybackPolicy::Signed->isSigned())->toBeTrue();
});

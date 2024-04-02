<?php

use Daun\StatamicMux\Support\URL;

test('appends params', function () {
    expect(URL::withQuery('/test', ['a' => 1, 'b' => 2]))->toEqual('/test?a=1&b=2');
});

test('ignores empty params', function () {
    expect(URL::withQuery('/test', []))->toEqual('/test');
    expect(URL::withQuery('/test', null))->toEqual('/test');
});

test('keeps host', function () {
    expect(URL::withQuery('https://local.test/test', ['a' => 1]))->toEqual('https://local.test/test?a=1');
});

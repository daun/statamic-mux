<?php

use Daun\StatamicMux\Support\Queue;

test('returns queue connection', function () {
    expect(Queue::connection())->toEqual(config('queue.default'));

    config(['mux.queue.connection' => 'mux-connection']);

    expect(Queue::connection())->toEqual('mux-connection');
});

test('returns queue name', function () {
    expect(Queue::queue())->toEqual('default');

    config(['mux.queue.queue' => 'mux-queue']);

    expect(Queue::queue())->toEqual('mux-queue');
});

test('returns queue sync state', function () {
    expect(Queue::isSync())->toBeTrue();

    config(['mux.queue.connection' => 'mux-queue']);

    expect(Queue::isSync())->toBeFalse();
});

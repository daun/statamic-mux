<?php

use Daun\StatamicMux\Support\Logging\Logger as PackageLogger;
use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\Log;
use Psr\Log\NullLogger;
use Tests\Support\InMemoryLogger;

/**
 * @return PackageLogger
 */
function makePackageLogger()
{
    return new PackageLogger(
        app(LogManager::class),
        config('mux.logging.channel', 'mux'),
        (bool) config('mux.logging.enabled', true),
    );
}

it('can be disabled from the config', function () {
    config()->set('mux.logging.enabled', false);
    config()->set('mux.logging.channel', 'mux');

    $logger = makePackageLogger()->resolveChannel();

    expect($logger)->toBeInstanceOf(NullLogger::class);
});

it('respects the channel from the config', function () {
    $inMemory = new InMemoryLogger;

    Log::extend('in-memory', fn () => $inMemory);

    config()->set('mux.logging.enabled', true);
    config()->set('mux.logging.channel', 'mux-in-memory');
    config()->set('logging.channels.mux-in-memory', [
        'driver' => 'in-memory',
    ]);

    $logger = makePackageLogger()->resolveChannel();

    $logger->debug('mux channel message');

    $records = $inMemory->recordsByLevel('debug');

    expect($records)->toHaveCount(1);
    expect($records[0]['message'])->toBe('mux channel message');
});

it('respects the level from the config', function () {
    config()->set('mux.logging.enabled', true);
    config()->set('mux.logging.channel', 'mux');
    config()->set('logging.channels.mux', null);
    config()->set('mux.logging.level', 'error');

    makePackageLogger();

    expect(config('logging.channels.mux.level'))->toBe('error');
});

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
    ray($logger);

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

it('redacts sensitive data from the output', function () {
    $inMemory = new InMemoryLogger;

    Log::extend('in-memory', fn () => $inMemory);

    config()->set('mux.logging.enabled', true);
    config()->set('mux.logging.channel', 'mux');
    config()->set('mux.logging.level', 'debug');

    $factory = makePackageLogger();

    config()->set('logging.channels.mux.driver', 'in-memory');

    $logger = $factory->resolveChannel();

    ray($logger);
    ray(config('mux.logging'));
    ray(config('logging'));

    $logger->debug('message with params', [
        'safe_param' => 'this is safe to log',
        'token_id' => 'dsg3f87f',
        'token_secret' => 'sdf9hsd9f8f7',
        'key_id' => '5hfdligj3s',
        'private_key' => '809dsfgju54htjdsf0fgujj45ifkrdsifghug8jsdugbshj4i8j',
    ]);

    $records = $inMemory->records;

    expect($records)->toHaveCount(1);
    expect($records[0]['message'])->toBe('message with params');
    expect($records[0]['context']['safe_param'])->toBe('this is safe to log');
    expect($records[0]['context']['token_id'])->toBe('dsg3****');
    expect($records[0]['context']['token_secret'])->toBe('sdf9********');
    expect($records[0]['context']['key_id'])->toBe('5hfd******');
    expect($records[0]['context']['private_key'])->toBe('809d************************************');
});

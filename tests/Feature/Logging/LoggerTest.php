<?php

use Daun\StatamicMux\Support\Logging\LogManager;
use Illuminate\Log\LogManager as IlluminateLog;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Psr\Log\NullLogger;
use RedactSensitive\RedactSensitiveProcessor;
use Tests\Support\InMemoryLogger;

/**
 * @return LogManager
 */
function makeLogManager()
{
    return new LogManager(
        app(IlluminateLog::class),
        config('mux.logging.channel', 'mux'),
        (bool) config('mux.logging.enabled', true),
    );
}

it('can be disabled from the config', function () {
    config()->set('mux.logging.enabled', false);
    config()->set('mux.logging.channel', 'mux');

    $logger = makeLogManager()->resolveChannel();

    expect($logger)->toBeInstanceOf(NullLogger::class);
});

it('respects the channel from the config', function () {
    $inMemory = new InMemoryLogger;

    Log::extend('in-memory', fn () => $inMemory);

    config()->set('mux.logging.enabled', true);
    config()->set('mux.logging.channel', 'mux-in-memory');
    config()->set('logging.channels.mux', null);
    config()->set('logging.channels.mux-in-memory', [
        'driver' => 'in-memory',
    ]);

    $logger = makeLogManager()->resolveChannel();

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

    makeLogManager();

    expect(config('logging.channels.mux.level'))->toBe('error');
});

it('redacts sensitive data from the output', function () {
    config()->set('mux.logging.enabled', true);
    config()->set('mux.logging.channel', 'mux');

    $logger = makeLogManager()->resolveChannel();

    // $laravelLogger is Illuminate\Log\Logger
    $ref = new \ReflectionClass($logger);
    $prop = $ref->getProperty('logger');
    $monolog = $prop->getValue($logger);

    // Ensure a clean slate of handlers to avoid noise (optional, but makes assertions deterministic)
    while ($monolog->getHandlers()) {
        $monolog->popHandler();
    }

    $testHandler = new TestHandler;
    $monolog->pushHandler($testHandler);

    $logger->debug('message with params', [
        'safe_param' => 'this is safe to log',
        'token_id' => 'dsg3f87f',
        'token_secret' => 'sdf9hsd9f8f7',
        'key_id' => '5hfdligj3s',
        'private_key' => '809dsfgju54htjdsf0fgujj45ifkrdsifghug8jsdugbshj4i8j',
    ]);

    // 4) Assert: processor ran and scrubbed context (b)
    $records = $testHandler->getRecords();
    expect($records)->toHaveCount(1);
    $context = $records[0]['context'];
    expect($context['safe_param'] ?? null)->toBe('this is safe to log');
    expect($context['token_id'] ?? null)->toBe('dsg3****');
    expect($context['token_secret'] ?? null)->toBe('sdf9********');
    expect($context['key_id'] ?? null)->toBe('5hfd******');
    expect($context['private_key'] ?? null)->toBe('809dsfgj*******************************************');

    // 5) Optional: assert (a) the processor is actually present via tap
    $processors = array_map(
        fn ($p) => is_object($p) ? get_class($p) : (is_array($p) ? 'callable' : gettype($p)),
        $monolog->getProcessors()
    );
    expect($processors)->toContain(RedactSensitiveProcessor::class);
});

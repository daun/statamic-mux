<?php

use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Support\Logging\LogManager;
use Daun\StatamicMux\Support\Logging\LogStream;
use Illuminate\Log\LogManager as IlluminateLog;
use Illuminate\Support\Facades\Log;
use Tests\Support\InMemoryLogger;

it('passes mux api debug output through the mux logger', function () {
    $logger = new InMemoryLogger;

    Log::extend('in-memory', function ($app, array $config) use ($logger) {
        return $logger;
    });

    config()->set('app.debug', true);
    config()->set('mux.logging.enabled', true);
    config()->set('mux.logging.level', 'debug');
    config()->set('mux.logging.channel', 'mux-in-memory');
    config()->set('logging.channels.mux-in-memory', [
        'driver' => 'in-memory',
    ]);

    $logManager = app(IlluminateLog::class);
    $resolvedLogger = (new LogManager($logManager, 'mux-in-memory', true))->resolveChannel();

    LogStream::register($resolvedLogger);

    $muxApi = app(MuxApi::class);
    expect($muxApi->config()->getDebug())->toBeTrue();
    expect($muxApi->config()->getDebugFile())->toBe('mux://debug');

    $stream = fopen($muxApi->config()->getDebugFile(), 'w');
    expect($stream)->not->toBeFalse();

    fwrite($stream, "debug line one\n debug line two ");
    fclose($stream);

    $records = $logger->recordsByLevel('debug');

    expect($records)->toHaveCount(2);
    expect($records[0]['message'])->toBe('debug line one');
    expect($records[1]['message'])->toBe('debug line two');
});

<?php

use Daun\StatamicMux\Jobs\CreateMuxAssetJob;
use Daun\StatamicMux\Mux\Actions\CreateMuxAsset;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Event;
use MuxPhp\ApiException;
use Statamic\Assets\Asset;

it('fails permanent HTTP client errors immediately', function (Closure $cause) {
    $asset = $this->uploadTestFileToTestContainer('test.mp4', 'permanent-failure.mp4');
    $exception = new Exception('Error uploading video to Mux', previous: $cause());
    $action = Mockery::mock(CreateMuxAsset::class);
    $action->shouldReceive('handle')
        ->once()
        ->with(Mockery::type(Asset::class), false)
        ->andThrow($exception);
    $this->app->instance(CreateMuxAsset::class, $action);

    $queuedJob = createMuxAssetQueueJob($this->app, new CreateMuxAssetJob($asset));

    $this->app['queue.worker']->process(
        'sync',
        $queuedJob,
        new WorkerOptions(maxTries: 5),
    );

    expect($queuedJob->maxTries())->toBe(3)
        ->and($queuedJob->backoff())->toBe('3,15')
        ->and($queuedJob->hasFailed())->toBeTrue()
        ->and($queuedJob->isReleased())->toBeFalse();
})->with([
    'Mux API response' => fn () => new ApiException('Invalid input', 422),
    'Guzzle response' => fn () => new RequestException(
        'Not found',
        new Request('POST', 'https://api.mux.com/video/v1/assets'),
        new Response(404),
    ),
]);

it('releases transient and unknown failures for retry', function (Closure $cause) {
    Event::fake([JobReleasedAfterException::class]);

    $asset = $this->uploadTestFileToTestContainer('test.mp4', 'retryable-failure.mp4');
    $exception = new Exception('Error uploading video to Mux', previous: $cause());
    $action = Mockery::mock(CreateMuxAsset::class);
    $action->shouldReceive('handle')
        ->once()
        ->with(Mockery::type(Asset::class), false)
        ->andThrow($exception);
    $this->app->instance(CreateMuxAsset::class, $action);

    $queuedJob = createMuxAssetQueueJob($this->app, new CreateMuxAssetJob($asset));

    expect(fn () => $this->app['queue.worker']->process(
        'sync',
        $queuedJob,
        new WorkerOptions(maxTries: 5),
    ))->toThrow(Exception::class, 'Error uploading video to Mux');

    expect($queuedJob->maxTries())->toBe(3)
        ->and($queuedJob->backoff())->toBe('3,15')
        ->and($queuedJob->hasFailed())->toBeFalse()
        ->and($queuedJob->isReleased())->toBeTrue();
    Event::assertDispatched(
        JobReleasedAfterException::class,
        fn (JobReleasedAfterException $event) => $event->job === $queuedJob && $event->backoff === 3,
    );
})->with([
    'request timeout' => fn () => new ApiException('Request timeout', 408),
    'rate limit' => fn () => new RequestException(
        'Too many requests',
        new Request('POST', 'https://api.mux.com/video/v1/assets'),
        new Response(429),
    ),
    'server error' => fn () => new ApiException('Mux unavailable', 503),
    'network error without response' => fn () => new RequestException(
        'Connection failed',
        new Request('POST', 'https://api.mux.com/video/v1/assets'),
    ),
    'unknown error' => fn () => new RuntimeException('Unexpected failure'),
]);

function createMuxAssetQueueJob($app, CreateMuxAssetJob $job): SyncJob
{
    $connection = $app['queue']->connection('sync');
    $payload = (function (CreateMuxAssetJob $job): string {
        return $this->createPayload($job, 'default');
    })->call($connection, $job);

    return new SyncJob($app, $payload, 'sync', 'default');
}

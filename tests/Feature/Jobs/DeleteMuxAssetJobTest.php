<?php

use Daun\StatamicMux\Jobs\DeleteMuxAssetJob;
use Daun\StatamicMux\Mux\Actions\DeleteMuxAsset;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Event;
use MuxPhp\ApiException;
use MuxPhp\Models\Asset as MuxApiAsset;

it('fails permanent HTTP client errors immediately', function (Closure $cause) {
    $exception = new Exception('Error deleting video from Mux', previous: $cause());
    $action = Mockery::mock(DeleteMuxAsset::class);
    $action->shouldReceive('handle')
        ->once()
        ->with('MUX-ASSET-ID')
        ->andThrow($exception);
    $this->app->instance(DeleteMuxAsset::class, $action);

    $queuedJob = createDeleteMuxAssetQueueJob($this->app, new DeleteMuxAssetJob('MUX-ASSET-ID'));

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
        'Forbidden',
        new Request('DELETE', 'https://api.mux.com/video/v1/assets/MUX-ASSET-ID'),
        new Response(403),
    ),
]);

it('releases transient and unknown failures for retry', function (Closure $cause) {
    Event::fake([JobReleasedAfterException::class]);

    $exception = new Exception('Error deleting video from Mux', previous: $cause());
    $action = Mockery::mock(DeleteMuxAsset::class);
    $action->shouldReceive('handle')
        ->once()
        ->with('MUX-ASSET-ID')
        ->andThrow($exception);
    $this->app->instance(DeleteMuxAsset::class, $action);

    $queuedJob = createDeleteMuxAssetQueueJob($this->app, new DeleteMuxAssetJob('MUX-ASSET-ID'));

    expect(fn () => $this->app['queue.worker']->process(
        'sync',
        $queuedJob,
        new WorkerOptions(maxTries: 5),
    ))->toThrow(Exception::class, 'Error deleting video from Mux');

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
        new Request('GET', 'https://api.mux.com/video/v1/assets/MUX-ASSET-ID'),
        new Response(429),
    ),
    'server error' => fn () => new ApiException('Mux unavailable', 503),
    'network error without response' => fn () => new RequestException(
        'Connection failed',
        new Request('DELETE', 'https://api.mux.com/video/v1/assets/MUX-ASSET-ID'),
    ),
    'unknown error' => fn () => new RuntimeException('Unexpected failure'),
]);

it('preserves a listed Mux asset through queue serialization', function () {
    $remoteAsset = new MuxApiAsset([
        'id' => 'MUX-ASSET-ID',
        'passthrough' => 'statamic::videos/example.mp4',
    ]);
    $action = Mockery::mock(DeleteMuxAsset::class);
    $action->shouldReceive('handle')
        ->once()
        ->with(Mockery::on(fn ($asset) => $asset instanceof MuxApiAsset
            && $asset->getId() === 'MUX-ASSET-ID'
            && $asset->getPassthrough() === 'statamic::videos/example.mp4'));
    $this->app->instance(DeleteMuxAsset::class, $action);

    $queuedJob = createDeleteMuxAssetQueueJob($this->app, new DeleteMuxAssetJob($remoteAsset));

    $this->app['queue.worker']->process(
        'sync',
        $queuedJob,
        new WorkerOptions(maxTries: 5),
    );

    expect($queuedJob->hasFailed())->toBeFalse();
});

function createDeleteMuxAssetQueueJob($app, DeleteMuxAssetJob $job): SyncJob
{
    $connection = $app['queue']->connection('sync');
    $payload = (function (DeleteMuxAssetJob $job): string {
        return $this->createPayload($job, 'default');
    })->call($connection, $job);

    return new SyncJob($app, $payload, 'sync', 'default');
}

<?php

use Daun\StatamicMux\Jobs\DeleteMuxAssetJob;
use Daun\StatamicMux\Jobs\DeleteReplacedMuxAssetJob;
use Daun\StatamicMux\Jobs\DownloadProxyVersionJob;
use Daun\StatamicMux\Mux\Actions\DownloadProxyVersion;
use Illuminate\Queue\Jobs\FakeJob;
use Illuminate\Support\Facades\Queue;
use MuxPhp\ApiException;

it('uses generic string-id cleanup after downloading a proxy', function () {
    Queue::fake();

    $asset = $this->uploadTestFileToTestContainer('test.mp4');
    $action = Mockery::mock(DownloadProxyVersion::class);
    $action->shouldReceive('shouldHandle')->once()->with($asset, 'PROXY-ID')->andReturnTrue();
    $action->shouldReceive('isReady')->once()->with($asset, 'PROXY-ID')->andReturnTrue();
    $action->shouldReceive('handle')->once()->with($asset, 'PROXY-ID')->andReturnTrue();

    (new DownloadProxyVersionJob($asset, 'PROXY-ID'))->handle($action);

    Queue::assertPushed(DeleteMuxAssetJob::class, function (DeleteMuxAssetJob $job) {
        $asset = (new ReflectionClass($job))->getProperty('asset')->getValue($job);

        return $asset === 'PROXY-ID';
    });
    Queue::assertNotPushed(DeleteReplacedMuxAssetJob::class);
});

it('fails permanent failures immediately', function (Closure $cause) {
    $asset = $this->uploadTestFileToTestContainer('test.mp4');
    $exception = $cause();
    $action = Mockery::mock(DownloadProxyVersion::class);
    $action->shouldReceive('shouldHandle')->once()->with($asset, 'PROXY-ID')->andReturnTrue();
    $action->shouldReceive('isReady')->once()->with($asset, 'PROXY-ID')->andThrow($exception);
    $action->shouldNotReceive('handle');

    $job = (new DownloadProxyVersionJob($asset, 'PROXY-ID'))->withFakeQueueInteractions();
    $job->handle($action);

    $job->assertFailedWith($exception)->assertNotReleased();
})->with([
    'unauthorized' => fn () => new ApiException('Unauthorized', 401),
    'gone' => fn () => new ApiException('Asset no longer exists', 404),
]);

it('leaves transient failures visible to the queue', function (Closure $cause) {
    $asset = $this->uploadTestFileToTestContainer('test.mp4');
    $action = Mockery::mock(DownloadProxyVersion::class);
    $action->shouldReceive('shouldHandle')->once()->with($asset, 'PROXY-ID')->andReturnTrue();
    $action->shouldReceive('isReady')->once()->with($asset, 'PROXY-ID')->andThrow($cause());
    $action->shouldNotReceive('handle');

    $job = (new DownloadProxyVersionJob($asset, 'PROXY-ID'))->withFakeQueueInteractions();

    expect(fn () => $job->handle($action))->toThrow(ApiException::class);

    $job->assertNotFailed();
})->with([
    'rate limit' => fn () => new ApiException('Too many requests', 429),
    'server error' => fn () => new ApiException('Mux unavailable', 503),
]);

it('retries for 24 hours with escalating release delays', function (int $attempt, int $delay) {
    $this->travelTo(now()->startOfHour());

    $asset = $this->uploadTestFileToTestContainer('test.mp4');
    $action = Mockery::mock(DownloadProxyVersion::class);
    $action->shouldReceive('shouldHandle')->once()->with($asset, 'PROXY-ID')->andReturnTrue();
    $action->shouldReceive('isReady')->once()->with($asset, 'PROXY-ID')->andReturnFalse();
    $action->shouldNotReceive('handle');

    $fakeJob = new FakeJob;
    $fakeJob->attempts = $attempt;
    $job = (new DownloadProxyVersionJob($asset, 'PROXY-ID'))->setJob($fakeJob);
    $job->handle($action);

    expect($job->retryUntil()->equalTo(now()->addDay()))->toBeTrue()
        ->and($job->backoff())->toBe([1, 3, 5, 10, 20, 30, 60, 120, 300, 600, 1200, 1800, 3600, 10800]);
    $job->assertReleased($delay);
})->with([
    'first attempt' => [1, 1],
    'fourth attempt' => [4, 10],
    'beyond the table' => [99, 10800],
]);

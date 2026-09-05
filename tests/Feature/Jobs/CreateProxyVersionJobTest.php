<?php

use Daun\StatamicMux\Jobs\CreateProxyVersionJob;
use Daun\StatamicMux\Jobs\DownloadProxyVersionJob;
use Daun\StatamicMux\Mux\Actions\CreateProxyVersion;
use Illuminate\Queue\Jobs\FakeJob;
use Illuminate\Support\Facades\Queue;
use MuxPhp\ApiException;

it('dispatches the download once a proxy has been requested', function () {
    Queue::fake();

    $asset = $this->uploadTestFileToTestContainer('test.mp4');
    $action = Mockery::mock(CreateProxyVersion::class);
    $action->shouldReceive('shouldHandle')->once()->with($asset)->andReturnTrue();
    $action->shouldReceive('isReady')->once()->with($asset)->andReturnTrue();
    $action->shouldReceive('handle')->once()->with($asset)->andReturn('PROXY-ID');

    (new CreateProxyVersionJob($asset))->handle($action);

    Queue::assertPushed(DownloadProxyVersionJob::class);
});

it('fails permanent failures immediately', function (Closure $cause) {
    $asset = $this->uploadTestFileToTestContainer('test.mp4');
    $exception = $cause();
    $action = Mockery::mock(CreateProxyVersion::class);
    $action->shouldReceive('shouldHandle')->once()->with($asset)->andReturnTrue();
    $action->shouldReceive('isReady')->once()->with($asset)->andThrow($exception);
    $action->shouldNotReceive('handle');

    $job = (new CreateProxyVersionJob($asset))->withFakeQueueInteractions();
    $job->handle($action);

    $job->assertFailedWith($exception)->assertNotReleased();
})->with([
    'unauthorized' => fn () => new ApiException('Unauthorized', 401),
    'gone' => fn () => new ApiException('Asset no longer exists', 404),
]);

it('leaves transient failures visible to the queue', function (Closure $cause) {
    $asset = $this->uploadTestFileToTestContainer('test.mp4');
    $action = Mockery::mock(CreateProxyVersion::class);
    $action->shouldReceive('shouldHandle')->once()->with($asset)->andReturnTrue();
    $action->shouldReceive('isReady')->once()->with($asset)->andThrow($cause());
    $action->shouldNotReceive('handle');

    $job = (new CreateProxyVersionJob($asset))->withFakeQueueInteractions();

    expect(fn () => $job->handle($action))->toThrow(ApiException::class);

    $job->assertNotFailed();
})->with([
    'rate limit' => fn () => new ApiException('Too many requests', 429),
    'server error' => fn () => new ApiException('Mux unavailable', 503),
]);

it('retries for 24 hours with escalating release delays', function (int $attempt, int $delay) {
    $this->travelTo(now()->startOfHour());

    $asset = $this->uploadTestFileToTestContainer('test.mp4');
    $action = Mockery::mock(CreateProxyVersion::class);
    $action->shouldReceive('shouldHandle')->once()->with($asset)->andReturnTrue();
    $action->shouldReceive('isReady')->once()->with($asset)->andReturnFalse();
    $action->shouldNotReceive('handle');

    $fakeJob = new FakeJob;
    $fakeJob->attempts = $attempt;
    $job = (new CreateProxyVersionJob($asset))->setJob($fakeJob);
    $job->handle($action);

    expect($job->retryUntil()->equalTo(now()->addDay()))->toBeTrue();
    $job->assertReleased($delay);
})->with([
    'first attempt' => [1, 1],
    'fourth attempt' => [4, 10],
    'beyond the table' => [99, 10800],
]);

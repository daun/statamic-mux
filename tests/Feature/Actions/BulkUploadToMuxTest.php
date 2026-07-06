<?php

use Daun\StatamicMux\Actions\BulkUploadToMux;
use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Http\Controllers\Cp\ListingReconciler;
use Daun\StatamicMux\Jobs\CreateMuxAssetJob;
use Illuminate\Support\Facades\Queue;
use Statamic\Facades\Stache;
use Statamic\Facades\User;

beforeEach(function () {
    config(['queue.default' => 'database']);
    Queue::fake();

    $this->addMirrorFieldToAssetBlueprint();

    $this->unuploaded = $this->uploadTestFileToTestContainer('test.mp4', 'unuploaded.mp4');
    $this->unuploaded->save();

    $this->uploaded = $this->uploadTestFileToTestContainer('test.mp4', 'uploaded.mp4');
    $this->uploaded->set('mux', ['id' => 'mux-asset-b']);
    $this->uploaded->save();

    Stache::clear();
});

function fakeCachedRemoteAssets(array $ids): void
{
    $remotes = collect($ids)->map(function (string $id) {
        $remote = Mockery::mock();
        $remote->shouldReceive('getId')->andReturn($id);

        return $remote;
    });

    $reconciler = Mockery::mock(ListingReconciler::class);
    $reconciler->shouldReceive('getCachedRemoteAssetsIfAvailable')->andReturn($remotes);
    app()->instance(ListingReconciler::class, $reconciler);
}

test('authorize requires manage mux permission', function () {
    $action = new BulkUploadToMux;

    expect($action->authorize(userWithMuxPermission('manage mux'), null))->toBeTrue();
    expect($action->authorize(User::make()->makeSuper(), null))->toBeTrue();
    expect($action->authorize(userWithMuxPermission('view mux library'), null))->toBeFalse();
    expect($action->authorize(userWithMuxPermission(null), null))->toBeFalse();
});

test('never visible in single-row dropdowns', function () {
    expect((new BulkUploadToMux)->visibleTo(MuxAsset::fromAsset($this->unuploaded)))->toBeFalse();
});

test('visible to bulk selection with mixed upload states', function () {
    $items = collect([
        MuxAsset::fromAsset($this->unuploaded),
        MuxAsset::fromAsset($this->uploaded),
    ]);

    expect((new BulkUploadToMux)->visibleToBulk($items))->toBeTrue();
});

test('not visible to bulk selection with matching states or proxies', function () {
    $action = new BulkUploadToMux;

    $sameState = collect([
        MuxAsset::fromAsset($this->unuploaded),
        new MuxAsset([], $this->unuploaded),
    ]);
    expect($action->visibleToBulk($sameState))->toBeFalse();

    $withProxy = collect([
        MuxAsset::fromAsset($this->uploaded),
        (new MuxAsset([], $this->unuploaded))->withProxy(),
    ]);
    expect($action->visibleToBulk($withProxy))->toBeFalse();
});

test('run only queues assets not already on mux', function () {
    Queue::fake();
    fakeCachedRemoteAssets(['mux-asset-b']);

    $result = (new BulkUploadToMux)->run(collect([
        MuxAsset::fromAsset($this->unuploaded),
        MuxAsset::fromAsset($this->uploaded),
    ]), []);

    Queue::assertPushed(CreateMuxAssetJob::class, 1);
    Queue::assertPushed(CreateMuxAssetJob::class, function ($job) {
        $asset = (new ReflectionClass($job))->getProperty('asset')->getValue($job);

        return $asset->id() === $this->unuploaded->id();
    });

    expect($result['callback'])->toBe(['pollMuxMirroredAssetRows', [$this->unuploaded->id()], 'upload']);
});

test('run reuploads existing assets when forced', function () {
    Queue::fake();
    fakeCachedRemoteAssets(['mux-asset-b']);

    (new BulkUploadToMux)->run(collect([MuxAsset::fromAsset($this->uploaded)]), ['force_reupload' => true]);

    Queue::assertPushed(CreateMuxAssetJob::class, function ($job) {
        $force = (new ReflectionClass($job))->getProperty('force')->getValue($job);

        return $force === true;
    });
});

test('run throws when nothing is queued', function () {
    Queue::fake();
    fakeCachedRemoteAssets(['mux-asset-b']);

    expect(fn () => (new BulkUploadToMux)->run(collect([MuxAsset::fromAsset($this->uploaded)]), []))
        ->toThrow(Exception::class);

    Queue::assertNothingPushed();
});

<?php

use Daun\StatamicMux\Actions\DeleteFromMux;
use Daun\StatamicMux\Data\Actions\MuxLibraryItem;
use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Http\Controllers\Cp\ListingReconciler;
use Daun\StatamicMux\Mux\MuxService;
use Statamic\Facades\Stache;
use Statamic\Facades\User;

beforeEach(function () {
    $this->addMirrorFieldToAssetBlueprint();

    $this->mp4 = $this->uploadTestFileToTestContainer('test.mp4');
    $this->mp4->set('mux', ['id' => 'mux-asset-001']);
    $this->mp4->save();

    Stache::clear();
});

test('authorize allows users with delete mux assets permission', function () {
    $action = new DeleteFromMux;

    expect($action->authorize(userWithMuxPermission('delete mux assets'), null))->toBeTrue();
    expect($action->authorize(User::make()->makeSuper(), null))->toBeTrue();
});

test('authorize denies users without delete mux assets permission', function () {
    $action = new DeleteFromMux;

    expect($action->authorize(userWithMuxPermission('manage mux'), null))->toBeFalse();
    expect($action->authorize(userWithMuxPermission(null), null))->toBeFalse();
});

test('visible to mux library items and existing mux assets', function () {
    $action = new DeleteFromMux;

    expect($action->visibleTo(new MuxLibraryItem('mux-asset-001')))->toBeTrue();
    expect($action->visibleTo(MuxAsset::fromAsset($this->mp4)))->toBeTrue();
});

test('not visible to mux assets without mux data or unrelated items', function () {
    $action = new DeleteFromMux;

    expect($action->visibleTo(new MuxAsset([], $this->mp4)))->toBeFalse();
    expect($action->visibleTo($this->getAssetContainer()))->toBeFalse();
    expect($action->visibleTo('not-a-mux-thing'))->toBeFalse();
});

test('run deletes the mux asset and returns a poll callback', function () {
    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('deleteMuxAsset')->once()->andReturnTrue();
    $this->app->instance(MuxService::class, $service);

    $reconciler = Mockery::mock(ListingReconciler::class);
    $reconciler->shouldReceive('forgetRemoteAsset')->once()->with('mux-asset-001');
    $this->app->instance(ListingReconciler::class, $reconciler);

    $result = (new DeleteFromMux)->run(collect([MuxAsset::fromAsset($this->mp4)]), []);

    expect($result)->toBeArray()
        ->and($result['callback'])->toBe(['pollMuxMirroredAssetRows', [$this->mp4->id()], 'delete']);
});

test('run throws when the only asset cannot be deleted', function () {
    $service = Mockery::mock(MuxService::class);
    $service->shouldReceive('deleteMuxAsset')->once()->andReturnFalse();
    $this->app->instance(MuxService::class, $service);

    $reconciler = Mockery::mock(ListingReconciler::class);
    $reconciler->shouldReceive('forgetRemoteAsset')->never();
    $this->app->instance(ListingReconciler::class, $reconciler);

    expect(fn () => (new DeleteFromMux)->run(collect([MuxAsset::fromAsset($this->mp4)]), []))
        ->toThrow(Exception::class);
});

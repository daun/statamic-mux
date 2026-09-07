<?php

use Daun\StatamicMux\Actions\RelinkToAsset;
use Daun\StatamicMux\Http\Controllers\Cp\ActionsController;
use Daun\StatamicMux\Mux\Enums\ReconciliationState;
use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\Reconciler;
use Daun\StatamicMux\Mux\RemoteAssetCache;
use Statamic\Facades\Stache;

/**
 * Action discovery and execution both build their items here, so the batch is
 * classified once and never per row.
 */
function muxRemoteItems(RemoteAssetCache $cache, Reconciler $reconciler, array $muxIds)
{
    $api = Mockery::mock(MuxApi::class);
    $api->shouldReceive('dashboardUrl')->andReturn(null);

    $controller = new class($cache, $api, $reconciler) extends ActionsController
    {
        public function remoteItems($items)
        {
            return $this->getRemoteItems($items);
        }
    };

    return $controller->remoteItems(collect($muxIds));
}

beforeEach(function () {
    config(['mux.mirror.enabled' => false]);
    $this->addMirrorFieldToAssetBlueprint();
    $this->video = $this->uploadTestFileToTestContainer('test.mp4');
    Stache::clear();

    $this->safe = muxRemoteVideo('safe-mux');
    $this->other = muxRemoteVideo('other-mux');
});

it('classifies a batch once and marks only the selected safe candidate', function () {
    $candidate = muxRemoteRecord($this->safe, ReconciliationState::Unlinked, $this->video);
    $plan = muxPlan(
        [muxLocalRecord($this->video, ReconciliationState::Unlinked, ['selected' => $candidate])],
        [$candidate, muxRemoteRecord($this->other, ReconciliationState::Foreign)],
    );

    $cache = Mockery::mock(RemoteAssetCache::class);
    $cache->shouldReceive('getIfAvailable')->once()->andReturn(collect([$this->safe->asset(), $this->other->asset()]));

    $reconciler = Mockery::mock(Reconciler::class);
    $reconciler->shouldReceive('fromRemoteAssets')->once()->andReturn($plan);

    $items = muxRemoteItems($cache, $reconciler, ['safe-mux', 'other-mux']);
    $action = new RelinkToAsset;

    expect($items)->toHaveCount(2)
        ->and($action->visibleTo($items->first()))->toBeTrue()
        ->and($action->visibleTo($items->last()))->toBeFalse();
});

it('stays conservative when the remote cache is cold', function () {
    $cache = Mockery::mock(RemoteAssetCache::class);
    $cache->shouldReceive('getIfAvailable')->once()->andReturn(collect());

    $reconciler = Mockery::mock(Reconciler::class);
    $reconciler->shouldNotReceive('fromRemoteAssets');
    $reconciler->shouldNotReceive('plan');

    $items = muxRemoteItems($cache, $reconciler, ['safe-mux']);

    expect((new RelinkToAsset)->visibleTo($items->first()))->toBeFalse();
});

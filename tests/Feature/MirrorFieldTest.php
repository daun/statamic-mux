<?php

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Fieldtypes\MuxMirrorFieldtype;
use Daun\StatamicMux\Support\MirrorField;
use Statamic\Assets\Asset;
use Statamic\Facades\Stache;
use Statamic\Fields\Field;

beforeEach(function () {
    Stache::clear();
});

/**
 * Build a configured MuxMirrorFieldtype instance bound to the given asset,
 * mirroring how Statamic resolves the fieldtype from the asset's blueprint.
 */
function preloadFieldtype(Asset $asset): MuxMirrorFieldtype
{
    $field = MirrorField::getFromBlueprint($asset)->setParent($asset);

    return $field->fieldtype();
}

test('checks if configured', function () {
    expect(MirrorField::configured())->toBeBool();
});

test('checks if enabled', function () {
    expect(MirrorField::enabled())->toBeBool();
    expect(MirrorField::enabled())->toBeTrue();
    config(['mux.mirror.enabled' => false]);
    expect(MirrorField::enabled())->toBeFalse();
    config(['mux.mirror.enabled' => true]);
    expect(MirrorField::enabled())->toBeTrue();
});

test('returns support for videos', function () {
    $mp4 = $this->uploadTestFileToTestContainer('test.mp4');
    $webm = $this->uploadTestFileToTestContainer('test.webm');
    $jpg = $this->uploadTestFileToTestContainer('test.jpg');
    expect(MirrorField::supportsAssetType($mp4))->toBeTrue();
    expect(MirrorField::supportsAssetType($webm))->toBeTrue();
    expect(MirrorField::supportsAssetType($jpg))->toBeFalse();
});

test('gets field instance from asset container blueprint', function () {
    $container = $this->getAssetContainer();
    expect(MirrorField::getFromBlueprint($container))->toBeNull();

    $this->addMirrorFieldToAssetBlueprint('mux_handle');
    expect(MirrorField::getFromBlueprint($container))->toBeInstanceOf(Field::class);
    expect(MirrorField::getFromBlueprint($container)->type())->toBe(MuxMirrorFieldtype::handle());
    expect(MirrorField::getFromBlueprint($container)->handle())->toBe('mux_handle');
});

test('gets field instance from asset blueprint', function () {
    $asset = $this->uploadTestFileToTestContainer('test.mp4');
    expect(MirrorField::getFromBlueprint($asset))->toBeNull();

    $this->addMirrorFieldToAssetBlueprint('another_handle');
    expect(MirrorField::getFromBlueprint($asset))->toBeInstanceOf(Field::class);
    expect(MirrorField::getFromBlueprint($asset)->type())->toBe(MuxMirrorFieldtype::handle());
    expect(MirrorField::getFromBlueprint($asset)->handle())->toBe('another_handle');
});

test('gets field handle from asset container blueprint', function () {
    $container = $this->getAssetContainer();
    expect(MirrorField::getHandle($container))->toBeNull();

    $this->addMirrorFieldToAssetBlueprint('some_handle');
    expect(MirrorField::getHandle($container))->toBe('some_handle');
});

test('gets field handle from asset blueprint', function () {
    $asset = $this->uploadTestFileToTestContainer('test.mp4');
    expect(MirrorField::getHandle($asset))->toBeNull();

    $this->addMirrorFieldToAssetBlueprint('last_handle');
    expect(MirrorField::getHandle($asset))->toBe('last_handle');
});

test('checks for existence of field in asset container blueprint', function () {
    $container = $this->getAssetContainer();
    expect(MirrorField::existsInBlueprint($container))->toBeFalse();

    $this->addMirrorFieldToAssetBlueprint();
    expect(MirrorField::existsInBlueprint($container))->toBeTrue();
});

test('checks for existence of field in asset blueprint', function () {
    $asset = $this->uploadTestFileToTestContainer('test.mp4');
    expect(MirrorField::existsInBlueprint($asset))->toBeFalse();

    $this->addMirrorFieldToAssetBlueprint();
    expect(MirrorField::existsInBlueprint($asset))->toBeTrue();
});

test('returns containers with mirror field', function () {
    $this->createAssetContainer('without');
    $this->createAssetContainer('with');
    $this->addMirrorFieldToAssetBlueprint(container: 'with');

    expect(MirrorField::containers()->map->handle()->all())->toEqual(['test_container_with']);
});

test('returns enabled assets with mirror field', function () {
    $mp4 = $this->uploadTestFileToTestContainer('test.mp4');
    $webm = $this->uploadTestFileToTestContainer('test.webm');
    $jpg = $this->uploadTestFileToTestContainer('test.jpg');

    expect(MirrorField::assets()->map->basename()->all())->toEqual([]);

    $this->addMirrorFieldToAssetBlueprint(container: 'with');
    expect(MirrorField::assets()->map->basename()->all())->toEqual(['test.mp4', 'test.webm']);
});

test('returns empty when no containers have a mirror field', function () {
    expect(MirrorField::assetsByMuxId('any-mux-id')->all())->toEqual([]);
});

test('finds assets by Mux id across containers', function () {
    $this->addMirrorFieldToAssetBlueprint();
    $this->addMirrorFieldToAssetBlueprint(container: 'private');

    $first = $this->uploadTestFileToTestContainer('test.mp4', 'first.mp4');
    $second = $this->uploadTestFileToTestContainer('test.mp4', 'second.mp4', container: 'private');
    $other = $this->uploadTestFileToTestContainer('test.mp4', 'other.mp4');

    MuxAsset::fromAsset($first)->withId('shared-mux-id')->save();
    MuxAsset::fromAsset($second)->withId('shared-mux-id')->save();
    MuxAsset::fromAsset($other)->withId('unrelated-mux-id')->save();
    Stache::clear();

    $results = MirrorField::assetsByMuxId('shared-mux-id');

    expect($results->map->id()->all())->toEqualCanonicalizing([$first->id(), $second->id()]);
});

test('excludes the given asset when finding by Mux id', function () {
    $this->addMirrorFieldToAssetBlueprint();

    $first = $this->uploadTestFileToTestContainer('test.mp4', 'first.mp4');
    $second = $this->uploadTestFileToTestContainer('test.mp4', 'second.mp4');

    MuxAsset::fromAsset($first)->withId('shared-mux-id')->save();
    MuxAsset::fromAsset($second)->withId('shared-mux-id')->save();
    Stache::clear();

    $results = MirrorField::assetsByMuxId('shared-mux-id', except: $first);

    expect($results->map->id()->all())->toEqual([$second->id()]);
});

test('preloads a video asset without mux data', function () {
    $this->addMirrorFieldToAssetBlueprint();
    $mp4 = $this->uploadTestFileToTestContainer('test.mp4');

    $data = preloadFieldtype($mp4)->preload();

    expect($data['is_asset'])->toBeTrue();
    expect($data['is_video'])->toBeTrue();
    expect($data['is_proxy'])->toBeFalse();
    expect($data['mux'])->toBe([]);
});

test('preloads a non-video asset', function () {
    $this->addMirrorFieldToAssetBlueprint();
    $jpg = $this->uploadTestFileToTestContainer('test.jpg');

    $data = preloadFieldtype($jpg)->preload();

    expect($data['is_asset'])->toBeTrue();
    expect($data['is_video'])->toBeFalse();
    expect($data['is_proxy'])->toBeFalse();
    expect($data['mux'])->toBe([]);
});

test('omits mux details when show_details is disabled', function () {
    $this->addMirrorFieldToAssetBlueprint('mux', ['show_details' => false]);
    $mp4 = $this->uploadTestFileToTestContainer('test.mp4');
    $mp4->set('mux', ['id' => 'mux-asset-001', 'playback_ids' => ['public' => 'playback-001']]);
    $mp4->save();
    Stache::clear();

    $data = preloadFieldtype($mp4)->preload();

    expect($data['is_video'])->toBeTrue();
    expect($data['mux'])->toBe([]);
});

test('includes mux details for a public video asset when show_details is enabled', function () {
    $this->addMirrorFieldToAssetBlueprint('mux', ['show_details' => true]);
    $mp4 = $this->uploadTestFileToTestContainer('test.mp4');
    $mp4->set('mux', ['id' => 'mux-asset-001', 'playback_ids' => ['public' => 'playback-001']]);
    $mp4->save();
    Stache::clear();

    $data = preloadFieldtype($mp4)->preload();

    expect($data['is_video'])->toBeTrue();
    expect($data['is_proxy'])->toBeFalse();
    expect($data['mux']['asset_id'])->toBe('mux-asset-001');
    expect($data['mux']['playback_id'])->toBe('playback-001');
    expect($data['mux']['signed'])->toBeFalse();
    expect($data['mux'])->toHaveKeys([
        'player_url',
        'stream_url',
        'thumbnail_url',
        'gif_url',
        'embed_code',
    ]);
});

test('reports signed playback ids in mux details', function () {
    $this->addMirrorFieldToAssetBlueprint('mux', ['show_details' => true]);
    $mp4 = $this->uploadTestFileToTestContainer('test.mp4');
    $mp4->set('mux', ['id' => 'mux-asset-002', 'playback_ids' => ['signed' => 'playback-signed-002']]);
    $mp4->save();
    Stache::clear();

    $data = preloadFieldtype($mp4)->preload();

    expect($data['mux']['asset_id'])->toBe('mux-asset-002');
    expect($data['mux']['playback_id'])->toBe('playback-signed-002');
    expect($data['mux']['signed'])->toBeTrue();
});

test('returns only the asset id when no playback id exists', function () {
    $this->addMirrorFieldToAssetBlueprint('mux', ['show_details' => true]);
    $mp4 = $this->uploadTestFileToTestContainer('test.mp4');
    $mp4->set('mux', ['id' => 'mux-asset-003']);
    $mp4->save();
    Stache::clear();

    $data = preloadFieldtype($mp4)->preload();

    expect($data['mux'])->toBe(['asset_id' => 'mux-asset-003']);
});

test('flags proxy assets', function () {
    $this->addMirrorFieldToAssetBlueprint('mux', ['show_details' => true]);
    $mp4 = $this->uploadTestFileToTestContainer('test.mp4');
    $mp4->set('mux', ['id' => 'mux-asset-004', 'playback_ids' => ['public' => 'playback-004'], 'is_proxy' => true]);
    $mp4->save();
    Stache::clear();

    $data = preloadFieldtype($mp4)->preload();

    expect($data['is_proxy'])->toBeTrue();
    expect($data['mux']['asset_id'])->toBe('mux-asset-004');
});

<?php

use Daun\StatamicMux\Fieldtypes\MuxMirrorFieldtype;
use Daun\StatamicMux\Support\MirrorField;
use Statamic\Facades\Stache;
use Statamic\Fields\Field;

beforeEach(function () {
    Stache::clear();
});

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

    $this->addMirrorFieldToAssetBlueprint();
    expect(MirrorField::getFromBlueprint($container))->toBeInstanceOf(Field::class);
    expect(MirrorField::getFromBlueprint($container)->type())->toBe(MuxMirrorFieldtype::handle());
});

test('gets field instance from asset blueprint', function () {
    $asset = $this->uploadTestFileToTestContainer('test.mp4');
    expect(MirrorField::getFromBlueprint($asset))->toBeNull();

    $this->addMirrorFieldToAssetBlueprint();
    expect(MirrorField::getFromBlueprint($asset))->toBeInstanceOf(Field::class);
    expect(MirrorField::getFromBlueprint($asset)->type())->toBe(MuxMirrorFieldtype::handle());
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

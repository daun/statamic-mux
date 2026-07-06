<?php

use Daun\StatamicMux\Data\MuxAsset;
use Daun\StatamicMux\Facades\Mux;
use Daun\StatamicMux\GraphQL\MuxMirrorType;
use Daun\StatamicMux\GraphQL\MuxPlaybackIdType;
use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Statamic\Facades\GraphQL;
use Statamic\Facades\Stache;
use Statamic\Fields\Value;
use Statamic\GraphQL\Types\JsonArgument;

beforeEach(function () {
    GraphQL::addType(MuxPlaybackIdType::class);
    GraphQL::addType(JsonArgument::class);

    $this->addMirrorFieldToAssetBlueprint();
});

function muxAssetWithData($test, array $mux): MuxAsset
{
    $mp4 = $test->uploadTestFileToTestContainer('test.mp4', 'test-'.uniqid().'.mp4');
    $mp4->set('mux', $mux);
    $mp4->save();

    Stache::clear();

    return MuxAsset::fromAsset($mp4);
}

function muxFields(): array
{
    return (new MuxMirrorType)->fields();
}

test('resolves the mux fields of a mirrored asset', function () {
    $asset = muxAssetWithData($this, ['id' => 123, 'playback_ids' => ['public' => 456]]);
    $fields = muxFields();

    expect($fields['mux_id']['resolve']($asset))->toBeInstanceOf(Value::class)
        ->and((string) $fields['mux_id']['resolve']($asset))->toBe('123');

    $playbackId = $fields['playback_id']['resolve']($asset, []);
    expect($playbackId->id())->toBe('456')
        ->and($playbackId->policy())->toBe('public');

    expect($fields['playback_ids']['resolve']($asset))->toHaveCount(1);
    expect($fields['playback_url']['resolve']($asset, []))->toBe('https://stream.mux.com/456.m3u8');
    expect($fields['thumbnail']['resolve']($asset, []))->toBe('https://image.mux.com/456/thumbnail.jpg');
    expect($fields['gif']['resolve']($asset, []))->toContain('https://image.mux.com/456/animated.gif');
});

test('filters the playback id by policy argument', function () {
    $asset = muxAssetWithData($this, ['id' => 123, 'playback_ids' => ['public' => 456, 'signed' => 789]]);
    $fields = muxFields();

    expect($fields['playback_id']['resolve']($asset, ['policy' => 'public'])->id())->toBe('456');
    expect($fields['playback_id']['resolve']($asset, ['policy' => 'signed'])->id())->toBe('789');
});

test('accepts valid policy arguments', function () {
    $asset = muxAssetWithData($this, ['id' => 123, 'playback_ids' => ['public' => 456]]);
    $fields = muxFields();

    expect(fn () => $fields['playback_url']['resolve']($asset, ['policy' => 'public']))->not->toThrow(ValidationException::class);
    expect(fn () => $fields['playback_url']['resolve']($asset, []))->not->toThrow(ValidationException::class);
});

test('rejects invalid policy arguments', function () {
    $asset = muxAssetWithData($this, ['id' => 123, 'playback_ids' => ['public' => 456]]);
    $fields = muxFields();

    expect(fn () => $fields['playback_url']['resolve']($asset, ['policy' => 'private']))->toThrow(ValidationException::class);
    expect(fn () => $fields['thumbnail']['resolve']($asset, ['policy' => 'bogus']))->toThrow(ValidationException::class);
});

test('returns null for assets without mux data instead of erroring', function () {
    $asset = muxAssetWithData($this, []);
    $fields = muxFields();

    expect($fields['playback_id']['resolve']($asset, []))->toBeNull();
    expect($fields['playback_url']['resolve']($asset, []))->toBeNull();
    expect($fields['thumbnail']['resolve']($asset, []))->toBeNull();
    expect($fields['playback_token']['resolve']($asset, []))->toBeNull();
    expect($fields['gif']['resolve']($asset, []))->toBeNull();
    expect($fields['placeholder']['resolve']($asset, []))->toBeNull();
});

test('resolves a signed jwt token when signing keys are configured', function () {
    config(['mux.playback_policy' => 'signed']);
    config(['mux.signing_key.key_id' => trim(File::get(fixtures_path('/keys/public.txt')))]);
    config(['mux.signing_key.private_key' => trim(File::get(fixtures_path('/keys/private.txt')))]);
    Mux::clearResolvedInstance(MuxService::class);

    $asset = muxAssetWithData($this, ['id' => 123, 'playback_ids' => ['signed' => 'signed-playback-id']]);
    $fields = muxFields();

    $token = $fields['playback_token']['resolve']($asset, []);

    expect($token)->toBeString()->toStartWith('eyJ');

    [$header, $payload] = explode('.', $token);
    $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

    expect($claims['sub'])->toBe('signed-playback-id');
});

test('exposes default playback modifiers', function () {
    $asset = muxAssetWithData($this, ['id' => 123, 'playback_ids' => ['public' => 456]]);
    $fields = muxFields();

    expect($fields['playback_modifiers']['resolve']())->toBe(Mux::getDefaultPlaybackModifiers());
});

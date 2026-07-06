<?php

use Daun\StatamicMux\Facades\Mux;
use Daun\StatamicMux\Mux\MuxService;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Stache;

beforeEach(function () {
    $this->addMirrorFieldToAssetBlueprint();

    $this->mp4 = $this->uploadTestFileToTestContainer('test.mp4');
    $this->mp4->set('mux', ['id' => 123, 'playback_ids' => ['public' => 456]]);
    $this->mp4->save();

    Stache::clear();
});

function configureSigningKeys(): void
{
    config(['mux.playback_policy' => 'signed']);
    config(['mux.signing_key.key_id' => trim(File::get(fixtures_path('/keys/public.txt')))]);
    config(['mux.signing_key.private_key' => trim(File::get(fixtures_path('/keys/private.txt')))]);

    // Rebuild the Mux service so it picks up the freshly configured signing keys
    Mux::clearResolvedInstance(MuxService::class);
}

function uploadSignedAsset($test): void
{
    $signed = $test->uploadTestFileToTestContainer('test.mp4', 'test-signed.mp4');
    $signed->set('mux', ['id' => 123, 'playback_ids' => ['signed' => 'signed-playback-id']]);
    $signed->save();

    Stache::clear();
}

function decodeJwtClaims(string $token): array
{
    [, $payload] = explode('.', $token);

    return json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
}

test('renders empty when missing variable in wildcard tag pair', function () {
    expect(fn () => $this->antlers('{{ mux:something }}{{ playback_id }}{{ /mux:something }}'))
        ->not->toThrow(Exception::class);

    $this->antlers('{{ mux:something }}{{ playback_id }}{{ /mux:something }}')
        ->assertDontSee('456');
});

test('renders empty when missing variable in wildcard tag', function () {
    expect(fn () => $this->antlers('{{ mux:something }}'))
        ->not->toThrow(Exception::class);
});

test('renders video component', function () {
    $this->antlers('{{ mux:video src="test_container_assets::test.mp4" }}')
        ->assertSeeInOrder([
            '<mux-video',
            'playback-id="456"',
            '></mux-video>',
        ], false);
});

test('applies params to video component', function () {
    $this->antlers('{{ mux:video src="test_container_assets::test.mp4" }}')
        ->assertDontSee('autoplay');

    $this->antlers('{{ mux:video src="test_container_assets::test.mp4" autoplay="true" loop="true" }}')
        ->assertSeeInOrder([
            '<mux-video',
            'autoplay',
            'loop',
        ], false)
        ->assertDontSee('muted');
});

test('embeds video scripts', function () {
    $this->antlers('{{ mux:video src="test_container_assets::test.mp4" }}')
        ->assertDontSee('<script async src="https://unpkg.com/@mux/mux-video@0"></script>', false);

    $this->antlers('{{ mux:video src="test_container_assets::test.mp4" script="true" }}')
        ->assertSee('<script async src="https://unpkg.com/@mux/mux-video@0"></script>', false);
});

test('lazyloads video scripts', function () {
    $this->antlers('{{ mux:video src="test_container_assets::test.mp4" }}')
        ->assertDontSee('<is-land', false);

    $this->antlers('{{ mux:video src="test_container_assets::test.mp4" script="true" lazyload="true" }}')
        ->assertSeeInOrder([
            'https://unpkg.com/@11ty/is-land',
            '<is-land on:visible>',
            '<script async src="https://unpkg.com/@mux/mux-video@0"></script>',
            '</is-land>',
        ], false);
});

test('renders player component', function () {
    $this->antlers('{{ mux:player src="test_container_assets::test.mp4" }}')
        ->assertSeeInOrder([
            '<mux-player',
            'playback-id="456"',
            '></mux-player>',
        ], false);
});

test('applies params to player component', function () {
    $this->antlers('{{ mux:player src="test_container_assets::test.mp4" }}')
        ->assertDontSee('autoplay');

    $this->antlers('{{ mux:player src="test_container_assets::test.mp4" autoplay="true" loop="true" }}')
        ->assertSeeInOrder([
            '<mux-player',
            'autoplay',
            'loop',
        ], false)
        ->assertDontSee('muted');

    $this->antlers('{{ mux:player src="test_container_assets::test.mp4" accent-color="#f00" }}')
        ->assertSeeInOrder([
            '<mux-player',
            'accent-color="#f00"',
        ], false);
});

test('embeds player scripts', function () {
    $this->antlers('{{ mux:player src="test_container_assets::test.mp4" }}')
        ->assertDontSee('<script async src="https://unpkg.com/@mux/mux-player@3"></script>', false);

    $this->antlers('{{ mux:player src="test_container_assets::test.mp4" script="true" }}')
        ->assertSee('<script async src="https://unpkg.com/@mux/mux-player@3"></script>', false);
});

test('lazyloads player scripts', function () {
    $this->antlers('{{ mux:player src="test_container_assets::test.mp4" }}')
        ->assertDontSee('<is-land', false);

    $this->antlers('{{ mux:player src="test_container_assets::test.mp4" script="true" lazyload="true" }}')
        ->assertSeeInOrder([
            'https://unpkg.com/@11ty/is-land',
            '<is-land on:visible>',
            '<script async src="https://unpkg.com/@mux/mux-player@3"></script>',
            '</is-land>',
        ], false);
});

test('renders iframe embed', function () {
    $this->antlers('{{ mux:embed src="test_container_assets::test.mp4" }}')
        ->assertSeeInOrder([
            '<iframe',
            'src="https://player.mux.com/456"',
            '></iframe>',
        ], false);
});

test('applies params to embed component', function () {
    $this->antlers('{{ mux:embed src="test_container_assets::test.mp4" }}')
        ->assertDontSee('autoplay');

    $this->antlers('{{ mux:embed src="test_container_assets::test.mp4" autoplay="true" loop="true" }}')
        ->assertSeeInOrder([
            '<iframe',
            'src="https://player.mux.com/456?autoplay=1&loop=1"',
        ], false)
        ->assertDontSee('muted');

    $this->antlers('{{ mux:embed src="test_container_assets::test.mp4" disable-tracking="true" }}')
        ->assertSeeInOrder([
            '<iframe',
            'src="https://player.mux.com/456?disable-tracking=1"',
        ], false);
});

test('renders signed video component with playback token', function () {
    configureSigningKeys();
    uploadSignedAsset($this);

    $this->antlers('{{ mux:video src="test_container_assets::test-signed.mp4" }}')
        ->assertSeeInOrder([
            '<mux-video',
            'playback-id="signed-playback-id?token=eyJ',
            '></mux-video>',
        ], false);
});

test('renders signed player component with token attributes', function () {
    configureSigningKeys();
    uploadSignedAsset($this);

    $this->antlers('{{ mux:player src="test_container_assets::test-signed.mp4" }}')
        ->assertSeeInOrder([
            '<mux-player',
            'playback-id="signed-playback-id"',
            'playback-token="eyJ',
            'thumbnail-token="eyJ',
            'storyboard-token="eyJ',
            '></mux-player>',
        ], false);
});

test('includes a signed token in the playback url', function () {
    configureSigningKeys();
    uploadSignedAsset($this);

    $url = (string) $this->antlers('{{ mux:playback_url src="test_container_assets::test-signed.mp4" }}');

    expect($url)->toContain('token=');

    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    $claims = decodeJwtClaims($query['token']);

    expect($claims['sub'])->toBe('signed-playback-id');
    expect($claims['aud'])->toBe('v');
});

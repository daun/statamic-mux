<?php

use Daun\StatamicMux\Support\CpAssets;

function cpAssetsManifestPath(): string
{
    return base_path('vendor/statamic/cms/resources/dist/build/manifest.json');
}

beforeEach(function () {
    $this->manifestBackup = null;

    if (is_file(cpAssetsManifestPath())) {
        $this->manifestBackup = file_get_contents(cpAssetsManifestPath());
    }

    @mkdir(dirname(cpAssetsManifestPath()), 0777, true);
    @unlink(cpAssetsManifestPath());
});

afterEach(function () {
    if ($this->manifestBackup !== null) {
        file_put_contents(cpAssetsManifestPath(), $this->manifestBackup);
    } else {
        @unlink(cpAssetsManifestPath());
    }
});

test('returns empty chunk urls when statamic manifest is missing', function () {
    expect(CpAssets::assetEditorChunkUrls())->toBe([]);
});

test('returns empty chunk urls when statamic manifest is invalid json', function () {
    file_put_contents(cpAssetsManifestPath(), '{not-json');

    expect(CpAssets::assetEditorChunkUrls())->toBe([]);
});

test('extracts unique asset editor chunk urls from statamic manifest imports', function () {
    file_put_contents(cpAssetsManifestPath(), json_encode([
        'resources/js/index.js' => [
            'imports' => ['chunk-vendor', 'chunk-editor', 'chunk-selector', 'chunk-missing', 'chunk-editor-copy'],
        ],
        'chunk-vendor' => ['file' => 'assets/vendor.123.js'],
        'chunk-editor' => ['file' => 'assets/AssetEditor.456.js'],
        'chunk-selector' => ['file' => 'assets/AssetSelector.789.js'],
        'chunk-editor-copy' => ['file' => 'assets/AssetEditor.456.js'],
    ], JSON_THROW_ON_ERROR));

    expect(CpAssets::assetEditorChunkUrls())->toBe([
        asset('vendor/statamic/cp/build/assets/AssetEditor.456.js'),
        asset('vendor/statamic/cp/build/assets/AssetSelector.789.js'),
        asset('vendor/statamic/cp/build/assets/vendor.123.js'),
    ]);
});

test('returns empty chunk urls when entry imports are missing', function () {
    file_put_contents(cpAssetsManifestPath(), json_encode([
        'resources/js/index.js' => [],
    ], JSON_THROW_ON_ERROR));

    expect(CpAssets::assetEditorChunkUrls())->toBe([]);
});

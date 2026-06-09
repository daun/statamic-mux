<?php

namespace Daun\StatamicMux\Support;

/**
 * Resolves URLs to Statamic's compiled control-panel JS chunks.
 *
 * Statamic 6 no longer ships the asset editor (`AssetEditor`) as a global component.
 * It's only bundled into an internal hashed chunk. So we need to resolve the chunk's
 * public URL and pass it to the addon for dynamic runtime imports.
 */
class CpAssets
{
    protected const MANIFEST_PATH = 'vendor/statamic/cms/resources/dist/build/manifest.json';

    protected const PUBLIC_PREFIX = 'vendor/statamic/cp/build/';

    protected const CP_ENTRY = 'resources/js/index.js';

    public static function assetEditorChunkUrls(): array
    {
        $manifest = static::manifest();

        if (! $manifest) {
            return [];
        }

        $imports = $manifest[static::CP_ENTRY]['imports'] ?? [];

        // Float likely chunks to front so resolution still works if Statamic reorganises its chunk
        return collect($imports)
            ->map(fn ($key) => $manifest[$key]['file'] ?? null)
            ->filter()
            ->unique()
            ->sortByDesc(fn (string $file) => preg_match('/selector|editor/i', basename($file)))
            ->values()
            ->map(fn (string $file) => asset(static::PUBLIC_PREFIX.$file))
            ->all();
    }

    protected static function manifest(): ?array
    {
        $path = base_path(static::MANIFEST_PATH);

        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }
}

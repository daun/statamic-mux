<?php

namespace Daun\StatamicMux\Placeholders;

use Illuminate\Support\Facades\Cache;

class PlaceholderService {
    public function forUrl(?string $url, ?string $key = null): ?string
    {
        if (!$url) return null;

        try {
            $key = $key ?: $url;
            return Cache::rememberForever(
                "mux-placeholder-uri--{$key}",
                fn() => $this->forBlob(file_get_contents($url))
            ) ?: null;
        } catch (\Throwable $th) {
            return null;
        }
    }

    protected function forBlob(?string $contents): ?string
    {
        if (!$contents) return null;

        try {
            $hash = $this->encode($contents);
            $uri = $this->decode($hash);
            return $uri;
        } catch (\Throwable $th) {
            return null;
        }
    }

    protected function encode(string $contents): ?string
    {
        return Thumbhash::encode($contents);
    }

    protected function decode(string $hash): ?string
    {
        return Thumbhash::decode($hash);
    }
}

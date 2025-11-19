<?php

namespace Daun\StatamicMux\Mux;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Daun\StatamicMux\Facades\Log;
use Daun\StatamicMux\Mux\Enums\MuxAudience;
use Daun\StatamicMux\Support\URL;
use Firebase\JWT\JWT;

class MuxUrls
{
    public function __construct(
        protected ?string $keyId,
        protected ?string $privateKey,
        protected int|string|null $defaultExpiration = null,
    ) {}

    /**
     * Create a URL for a playback stream
     */
    public function playback(string $playbackId): string
    {
        return "https://stream.mux.com/{$playbackId}.m3u8";
    }

    /**
     * Create a URL for generating a thumbnail
     */
    public function thumbnail(string $playbackId, string $format = 'jpg'): string
    {
        return "https://image.mux.com/{$playbackId}/thumbnail.{$format}";
    }

    /**
     * Create a URL for generating an animated gif
     */
    public function animated(string $playbackId, string $format = 'gif'): string
    {
        return "https://image.mux.com/{$playbackId}/animated.{$format}";
    }

    /**
     * Create a URL for downloading a static rendition
     */
    public function download(string $playbackId, string $rendition, string $filename = 'download'): string
    {
        return "https://stream.mux.com/{$playbackId}/{$rendition}?download={$filename}";
    }

    /**
     * Sign a URL for a given playback id and params
     */
    public function sign(string $url, string $playbackId, MuxAudience $audience, ?array $params = null, int|string|null $expiration = null): string
    {
        $token = $this->token($playbackId, $audience, $params, $expiration);

        $signed = $token
            ? URL::withQuery($url, ['token' => $token])
            : URL::withQuery($url, $params);

        Log::debug("Signed url: {$signed}", [
            'url' => $url,
            'token' => $token,
            'audience' => $audience,
            'params' => $params,
        ]);

        return $signed;
    }

    /**
     * Generate a signing token for a Mux playback id and given params
     */
    public function token(string $playbackId, MuxAudience $audience, ?array $params = null, int|string|null $expiration = null): ?string
    {
        if (! $this->keyId || ! $this->privateKey) {
            throw new \Exception('Missing Mux signing keys');
        }

        if (empty($playbackId)) {
            throw new \Exception('Empty Mux playback id');
        }

        $timestamp = $this->timestamp($expiration);

        $claims = array_merge([
            'sub' => $playbackId,
            'aud' => $audience,
            'exp' => $timestamp,
            'kid' => $this->keyId,
        ], $params ?? []);

        try {
            return JWT::encode($claims, base64_decode($this->privateKey), 'RS256');
        } catch (\Throwable $th) {
            Log::error("Error encoding url token: {$th->getMessage()}", [
                'payload' => $claims,
                'playback_id' => $playbackId,
                'key_id' => $this->keyId,
                'private_key' => $this->privateKey,
            ]);

            return null;
        }
    }

    /**
     * Convert a time expression into a Unix timestamp
     */
    public function timestamp(int|string|null $expiration = null): int
    {
        $expiration = $expiration ?? $this->defaultExpiration ?? 0;

        $interval = match (true) {
            is_string($expiration) => CarbonInterval::make($expiration),
            is_int($expiration) => CarbonInterval::make($expiration, 'seconds'),
            default => CarbonInterval::make(0, 'seconds'),
        };

        return Carbon::now()->add($interval)->timestamp;
    }
}

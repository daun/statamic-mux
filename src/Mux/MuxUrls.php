<?php

namespace Daun\StatamicMux\Mux;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Firebase\JWT\JWT;

class MuxUrls
{
    public const AUDIENCE_GIF = 'g';

    public const AUDIENCE_STORYBOARD = 's';

    public const AUDIENCE_THUMBNAIL = 't';

    public const AUDIENCE_VIDEO = 'v';

    public function __construct(
        protected ?string $keyId,
        protected ?string $privateKey,
        protected int|string|null $defaultExpiration = null,
    ) {
    }

    public function getToken(string $playbackId, string $audience, ?array $params = null, int|string|null $expiration = null): ?string
    {
        if (! $this->keyId || ! $this->privateKey) {
            throw new \Exception('Missing Mux signing key');
        }

        if (empty($playbackId)) {
            throw new \Exception('Empty Mux playback id');
        }

        if (! $this->isValidAudience($audience)) {
            throw new \Exception("Invalid Mux audience key '{$audience}'");
        }

        $claims = array_merge([
            'sub' => $playbackId,
            'aud' => $audience,
            'exp' => $this->getExpirationTimestamp($expiration),
            'kid' => $this->keyId,
        ], $params ?? []);

        return JWT::encode($claims, base64_decode($this->privateKey), 'RS256');
    }

    protected function getExpirationTimestamp(int|string|null $expiration): int
    {
        $expiration = $expiration ?? $this->defaultExpiration ?? 0;
        $interval = match (true) {
            is_string($expiration) => CarbonInterval::make($expiration),
            is_int($expiration) => CarbonInterval::make($expiration, 'seconds'),
            default => CarbonInterval::make(0, 'seconds'),
        };

        return Carbon::now()->add($interval)->timestamp;
    }

    protected function isValidAudience(?string $audience): bool
    {
        return in_array($audience, [
            static::AUDIENCE_GIF,
            static::AUDIENCE_STORYBOARD,
            static::AUDIENCE_THUMBNAIL,
            static::AUDIENCE_VIDEO,
        ]);
    }
}

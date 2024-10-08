<?php

namespace Daun\StatamicMux\Data;

use Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Statamic\Support\Traits\FluentlyGetsAndSets;

class MuxPlaybackId implements Arrayable
{
    use FluentlyGetsAndSets;

    public function __construct(
        protected string $id,
        protected string $policy
    ) {
    }

    public static function make(string $id, string $policy): ?static
    {
        if ($id && $policy) {
            return new static($id, $policy);
        } else {
            return null;
        }
    }

    public function id($id = null)
    {
        return $this->fluentlyGetOrSet('id')->args(func_get_args());
    }

    public function policy($policy = null)
    {
        return $this->fluentlyGetOrSet('policy')->args(func_get_args());
    }

    public function hasPolicy(MuxPlaybackPolicy $policy): bool
    {
        return MuxPlaybackPolicy::make($this->policy)?->is($policy) ?? false;
    }

    public function isPublic(): bool
    {
        return MuxPlaybackPolicy::make($this->policy)?->isPublic() ?? false;
    }

    public function isSigned(): bool
    {
        return MuxPlaybackPolicy::make($this->policy)?->isSigned() ?? false;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'policy' => $this->policy,
        ];
    }
}

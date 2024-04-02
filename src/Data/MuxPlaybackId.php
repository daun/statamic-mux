<?php

namespace Daun\StatamicMux\Data;

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

    public static function make(?array $data = []): ?static
    {
        if (Arr::has($data, ['id', 'policy'])) {
            return new static($data['id'], $data['policy']);
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

    public function public(): bool
    {
        return $this->policy === 'public';
    }

    public function signed(): bool
    {
        return $this->policy === 'signed';
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'policy' => $this->policy,
        ];
    }
}

<?php

namespace Daun\StatamicMux\Data;

use Daun\StatamicMux\Data\Augmentables\AugmentedMuxAsset;
use Daun\StatamicMux\Facades\Mux;
use Daun\StatamicMux\Support\MirrorField;
use Statamic\Assets\Asset;
use Statamic\Contracts\Data\Augmentable;
use Statamic\Data\ContainsData;
use Statamic\Data\HasAugmentedInstance;

class MuxAsset implements Augmentable
{
    use ContainsData;
    use HasAugmentedInstance;

    public ?Asset $asset;

    public ?string $field;

    public function __construct(?array $data, ?Asset $asset = null, ?string $field = null)
    {
        $this->data = collect($data ?? []);
        $this->asset = $asset;
        $this->field = $field ?? MirrorField::getFromBlueprint($asset)?->handle();
    }

    public static function fromAsset(Asset $asset, ?string $field = null): static
    {
        $field = $field ?? MirrorField::getFromBlueprint($asset)?->handle();
        $data = $field ? $asset->get($field) : [];

        return new static($data, $asset, $field);
    }

    public function id(): ?string
    {
        return $this->get('id');
    }

    public function exists(): bool
    {
        return $this->has('id');
    }

    public function existsOnMux(): bool
    {
        return $this->exists() && Mux::muxAssetExists($this->id());
    }

    public function save(): void
    {
        if (! $this->asset || ! $this->field) {
            return;
        }

        $data = $this->data->toArray();
        $this->asset->set($this->field, $data);
        $this->asset->saveQuietly();
    }

    public function refresh(): void
    {
        if (! $this->asset || ! $this->field) {
            return;
        }

        $data = $this->asset->get($this->field);
        $this->data = collect($data ?? []);
    }

    public function clear(): void
    {
        $this->data = collect([]);
    }

    public function playbackIds(): MuxPlaybackIds
    {
        if ($this->get('playback_id')) {
            return MuxPlaybackIds::make([[
                'id' => $this->get('playback_id'),
                'policy' => $this->get('playback_policy'),
            ]]);
        }

        return MuxPlaybackIds::make($this->get('playback_ids', []));
    }

    public function playbackId(): ?MuxPlaybackId
    {
        $playbackIds = $this->playbackIds();

        return $playbackIds->public()
            ?: $playbackIds->signed()
            ?: null;
    }

    public function newAugmentedInstance(): AugmentedMuxAsset
    {
        return new AugmentedMuxAsset($this);
    }
}

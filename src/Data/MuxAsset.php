<?php

namespace Daun\StatamicMux\Data;

use Daun\StatamicMux\Data\Augmentables\AugmentedMuxAsset;
use Daun\StatamicMux\Facades\Mux;
use Daun\StatamicMux\Mux\Enums\MuxPlaybackPolicy;
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

    protected ?MuxPlaybackIds $playbackIds = null;

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

    public function duration(): ?float
    {
        return ($duration = $this->get('duration'))
            ? floatval($duration)
            : null;
    }

    public function existsOnMux(): bool
    {
        return $this->id() && Mux::api()->assetExists($this->id());
    }

    public function isProxy(): bool
    {
        return (bool) $this->get('is_proxy', false);
    }

    public function save(): self
    {
        if ($this->asset && $this->field) {
            $data = $this->data->toArray();
            $this->asset->set($this->field, $data);
            $this->asset->saveQuietly();
        }

        return $this;
    }

    public function refresh(): self
    {
        if ($this->asset && $this->field) {
            $data = $this->asset->get($this->field);
            $this->data = collect($data ?? []);
        }

        return $this;
    }

    public function clear(): self
    {
        $this->data = collect([]);

        return $this;
    }

    public function playbackIds(): MuxPlaybackIds
    {
        return $this->playbackIds ??= MuxPlaybackIds::make($this->get('playback_ids', []));
    }

    public function playbackId(?MuxPlaybackPolicy $policy = null): ?MuxPlaybackId
    {
        $playbackIds = $this->playbackIds();

        return $policy
            ? $playbackIds->findWithPolicy($policy)
            : ($playbackIds->findPublic() ?? $playbackIds->findSigned());
    }

    public function addPlaybackId(string $id, string $policy): ?MuxPlaybackId
    {
        $playbackId = $this->playbackIds()->addPlaybackId($id, $policy);
        $this->set('playback_ids', $this->playbackIds()->toArray());
        return $playbackId;
    }

    public function setId(?string $id): self
    {
        $this->set('id', $id);

        return $this;
    }

    public function setProxy(bool $proxy = true): self
    {
        $this->set('is_proxy', $proxy);

        return $this;
    }

    public function setDuration(float|null $duration): self
    {
        $this->set('duration', $duration);

        return $this;
    }

    public function newAugmentedInstance(): AugmentedMuxAsset
    {
        return new AugmentedMuxAsset($this);
    }
}

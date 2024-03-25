<?php

namespace Daun\StatamicMux\Support\Traits;

use Closure;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;

trait Hookable
{
    private static $hooks = [];

    public static function hook(string $hook, Closure $callback): void
    {
        static::$hooks[static::class][$hook][] = $callback;
    }

    protected function getHookCallbacks(string $hook): Collection
    {
        return collect(static::$hooks[static::class][$hook] ?? []);
    }

    protected function runHooks(string $name, $payload = null): mixed
    {
        $callbacks = $this->getHookCallbacks($name)->map->bindTo($this, $this);

        return (new Pipeline)
            ->send($payload)
            ->through($callbacks->all())
            ->thenReturn();
    }
}

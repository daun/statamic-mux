<?php

namespace Daun\StatamicMux\Concerns;

use Closure;
use Illuminate\Pipeline\Pipeline;

trait ProcessesHooks
{
    public static function hook(string $name, Closure $hook)
    {
        $hooks = app('mux.hooks');

        $hooks[$name] ??= collect();

        $hooks[$name][] = $hook;
    }

    protected function hooks(string $name, ?array $payload = null)
    {
        $closures = collect(app('mux.hooks')[$name] ?? [])->map->bindTo($this, $this);

        if (is_array($payload)) {
            $payload = (object) $payload;
        }

        if ($closures->isEmpty()) {
            return $payload;
        }

        return (new Pipeline)
            ->send($payload)
            ->through($closures->all())
            ->thenReturn();
    }
}

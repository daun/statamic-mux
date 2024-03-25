<?php

namespace Daun\StatamicMux\Tags\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Statamic\Tags\Concerns\RendersAttributes;

trait RendersHtml
{
    use RendersAttributes;

    /**
     * Turn query_params into html-attributes (snake to kebab case)
     */
    protected function toHtmlAttributes(mixed $params): Collection
    {
        return collect($params)->keyBy(fn ($_, $key) => Str::replace('_', '-', $key));
    }
}

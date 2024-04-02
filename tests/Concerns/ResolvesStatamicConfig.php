<?php

namespace Tests\Concerns;

trait ResolvesStatamicConfig
{
    protected function resolveStatamicConfiguration($app)
    {
        foreach (glob(statamic_package_path('config/*.php')) as $path) {
            $key = basename($path, '.php');
            $app['config']->set("statamic.{$key}", require $path);
        }
    }

    protected function resolveStacheStores($app)
    {
        $stores = [
            'taxonomies' => 'content/taxonomies',
            'terms' => 'content/terms',
            'collections' => 'content/collections',
            'entries' => 'content/collections',
            'navigation' => 'content/navigation',
            'collection-trees' => 'content/trees/collections',
            'nav-trees' => 'content/trees/navigation',
            'globals' => 'content/globals',
            'global-variables' => 'content/globals',
            'asset-containers' => 'content/assets',
            'users' => 'users',
        ];

        foreach ($stores as $store => $path) {
            $app['config']->set("statamic.stache.stores.{$store}.directory", fixtures_path($path));
        }
    }
}

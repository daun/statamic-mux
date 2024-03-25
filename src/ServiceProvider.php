<?php

namespace Daun\StatamicMux;

use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Mux\MuxUrls;
use Daun\StatamicMux\Fieldtypes;
use Daun\StatamicMux\Placeholders\PlaceholderService;
use Illuminate\Foundation\Application;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Events;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;

class ServiceProvider extends AddonServiceProvider
{
    protected $commands = [
        Commands\Mirror::class,
        Commands\Prune::class,
        Commands\Upload::class,
    ];

    protected $listen = [
        // Events\AssetSaved::class => [Listeners\CreateMuxAsset::class],
        Events\AssetUploaded::class => [Listeners\CreateMuxAsset::class],
        Events\AssetReuploaded::class => [Listeners\CreateMuxAsset::class],
        Events\AssetDeleted::class => [Listeners\DeleteMuxAsset::class],
    ];

    protected $fieldtypes = [
        Fieldtypes\MuxMirror::class,
    ];

    protected $tags = [
        Tags\MuxTags::class,
    ];

    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
    ];

    protected $vite = [
        'input' => [
            'resources/js/addon.js',
            'resources/css/addon.css',
        ],
        'publicDirectory' => 'resources/dist',
    ];

    public function register()
    {
        $this->registerAddonConfig();
        $this->registerMuxApi();
        $this->registerMuxService();
        $this->registerUrlService();
        $this->registerPlaceholderService();
    }

    public function bootAddon()
    {
        $this->bootAddonViews();
        $this->bootAddonNav();
        $this->bootPermissions();
    }

    protected function registerMuxApi()
    {
        $this->app->singleton(MuxApi::class, function (Application $app) {
            return new MuxApi(
                $app['config']->get('mux.credentials.token_id'),
                $app['config']->get('mux.credentials.token_secret'),
                $app['config']->get('app.debug', false),
                $app['config']->get('mux.test_mode', false),
                $app['config']->get('mux.playback_policy', null),
                $app['config']->get('mux.encoding_tier', null),
            );
        });
        $this->app->alias(MuxApi::class, 'mux.api');
    }

    protected function registerMuxService()
    {
        $this->app->singleton(MuxService::class, function (Application $app) {
            return new MuxService(
                $app,
                $app['mux.api'],
                $app['mux.urls'],
                $app['mux.placeholders'],
            );
        });
        $this->app->alias(MuxService::class, 'mux.service');
    }

    protected function registerUrlService()
    {
        $this->app->singleton(MuxUrls::class, function (Application $app) {
            return new MuxUrls(
                $app['config']->get('mux.signing_key.key_id'),
                $app['config']->get('mux.signing_key.private_key'),
                $app['config']->get('mux.signing_key.expiration'),
            );
        });
        $this->app->alias(MuxUrls::class, 'mux.urls');
    }

    protected function registerPlaceholderService()
    {
        $this->app->singleton(PlaceholderService::class, function (){
            return new PlaceholderService();
        });
        $this->app->alias(PlaceholderService::class, 'mux.placeholders');
    }

    protected function registerAddonConfig()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mux.php', 'mux');

        $this->publishes([
            __DIR__.'/../config/mux.php' => config_path('statamic/mux.php'),
        ], 'statamic-mux');
    }

    protected function bootAddonViews()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mux');
    }

    protected function bootAddonNav()
    {
        Nav::extend(function ($nav) {
            $nav->tools('Mux')
                ->route('mux.index')
                ->icon('video')
                ->active('mux')
                ->can('view mux')
                ->children([
                    'Assets' => cp_route('mux.assets.index'),
                ]);
        });
    }


    protected function bootPermissions()
    {
        Permission::group('mux', 'Mux', function () {
            Permission::register('view mux', function ($permission) {
                $permission
                    ->label('View Mux Assets')
                    ->children([
                        Permission::make('edit mux')
                            ->label('Edit Mux Assets')
                            ->children([
                                Permission::make('create mux')->label('Create Mux Assets'),
                                Permission::make('delete mux')->label('Delete Mux Assets'),
                            ]),
                    ]);
            });
        });
    }
}

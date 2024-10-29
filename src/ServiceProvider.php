<?php

namespace Daun\StatamicMux;

use Daun\StatamicMux\Mux\MuxApi;
use Daun\StatamicMux\Mux\MuxService;
use Daun\StatamicMux\Mux\MuxUrls;
use Daun\StatamicMux\Placeholders\PlaceholderService;
use GuzzleHttp\Client;
use Illuminate\Foundation\Application;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Statamic;

class ServiceProvider extends AddonServiceProvider
{
    protected $commands = [
        Commands\MirrorCommand::class,
        Commands\PruneCommand::class,
        Commands\UploadCommand::class,
    ];

    protected $subscribe = [
        Subscribers\MirrorFieldSubscriber::class,
    ];

    protected $fieldtypes = [
        Fieldtypes\MuxMirrorFieldtype::class,
    ];

    protected $tags = [
        Tags\MuxTags::class,
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
        $this->registerMuxApi();
        $this->registerMuxService();
        $this->registerUrlService();
        $this->registerPlaceholderService();
    }

    public function bootAddon()
    {
        $this->bootPermissions();
        $this->autoPublishConfig();
        $this->publishViews();
    }

    protected function registerMuxApi()
    {
        $this->app->bind(MuxApi::class, function (Application $app) {
            return new MuxApi(
                new Client,
                $app['config']->get('mux.credentials.token_id'),
                $app['config']->get('mux.credentials.token_secret'),
                $app['config']->get('app.debug', false),
                $app['config']->get('mux.test_mode', false),
                $app['config']->get('mux.playback_policy', null),
                $app['config']->get('mux.video_quality', null),
            );
        });
        $this->app->alias(MuxApi::class, 'mux.api');
    }

    protected function registerMuxService()
    {
        $this->app->bind(MuxService::class, function (Application $app) {
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
        $this->app->bind(MuxUrls::class, function (Application $app) {
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
        $this->app->bind(PlaceholderService::class, function () {
            return new PlaceholderService;
        });
        $this->app->alias(PlaceholderService::class, 'mux.placeholders');
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

    /**
     * Modified version of parent `bootConfig` method to customize
     * the config file name.
     */
    protected function bootConfig()
    {
        $filename = 'mux';
        $directory = $this->getAddon()->directory();
        $origin = "{$directory}config/{$filename}.php";

        if (! $this->config || ! file_exists($origin)) {
            return $this;
        }

        $this->mergeConfigFrom($origin, $filename);

        $this->publishes([
            $origin => config_path("{$filename}.php"),
        ], "{$filename}-config");

        return parent::bootConfig();
    }

    protected function publishViews(): self
    {
        $addon = $this->getAddon()->slug();

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/statamic-mux')
        ], "{$addon}-views");

        return $this;
    }

    protected function autoPublishConfig(): self
    {
        Statamic::afterInstalled(function ($command) {
            $command->call('vendor:publish', ['--tag' => 'mux-config']);
        });

        return $this;
    }

    public function provides(): array
    {
        return [
            MuxApi::class,
            'mux.api',
            MuxService::class,
            'mux.service',
            MuxUrls::class,
            'mux.urls',
            PlaceholderService::class,
            'mux.placeholders',
        ];
    }
}

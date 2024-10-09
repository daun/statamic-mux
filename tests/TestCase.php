<?php

namespace Tests;

use Daun\StatamicMux\ServiceProvider as AddonServiceProvider;
use Illuminate\Foundation\Testing\Concerns\InteractsWithViews;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Statamic\Extend\Manifest;
use Statamic\Providers\StatamicServiceProvider;
use Statamic\Statamic;
use Tests\Concerns\DealsWithAssets;
use Tests\Concerns\ExtendsAssetBlueprint;
use Tests\Concerns\InteractsWithAntlersViews;
use Tests\Concerns\PreventSavingStacheItemsToDisk;
use Tests\Concerns\ResolvesStatamicConfig;
use Wilderborn\Partyline\ServiceProvider as PartyLineServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    use DealsWithAssets;
    use ExtendsAssetBlueprint;
    use InteractsWithAntlersViews;
    use InteractsWithViews;
    use PreventSavingStacheItemsToDisk;
    use ResolvesStatamicConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpAssetTest();
    }

    protected function tearDown(): void
    {
        $this->tearDownAssetTest();

        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [
            AddonServiceProvider::class,
            PartyLineServiceProvider::class,
            StatamicServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Statamic' => Statamic::class,
        ];
    }

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        // Custom view directory
        $app['config']->set('view.paths', [fixtures_path('views')]);

        // Pull in statamic default config
        $this->resolveStatamicConfiguration($app);

        // Rewrite content paths to use our test fixtures
        $this->resolveStacheStores($app);

        // Set user repository to default flat file system
        $app['config']->set('statamic.users.repository', 'file');

        // Assume pro edition for our tests
        $app['config']->set('statamic.editions.pro', true);

        // Set specific config for asset tests
        $this->resolveApplicationConfigurationForAssetTest($app);

        // Set specific stache stores for asset tests
        $this->resolveStacheStoresForAssetTest($app);
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $this->registerStatamicAddon($app);
    }

    protected function registerStatamicAddon($app)
    {
        $app->make(Manifest::class)->manifest = [
            'daun/statamic-mux' => [
                'id' => 'daun/statamic-mux',
                'namespace' => 'Daun\\StatamicMux',
            ],
        ];
    }
}

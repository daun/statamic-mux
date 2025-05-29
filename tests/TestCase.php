<?php

namespace Tests;

use BlastCloud\Guzzler\Expectation;
use BlastCloud\Guzzler\UsesGuzzler;
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
    use UsesGuzzler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpAssetTest();

        // Create Guzzler helper: $this->guzzer->get()->debug();
        Expectation::macro('ray', function (Expectation $e) {
            return $e->withCallback(function (array $history) {
                ray($history['request']->getBody()->getContents())->label('Body');
                ray($history['request'])->label('Request');
                return true;
            });
        });
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
        $reflector = new \ReflectionClass(AddonServiceProvider::class);
        $directory = dirname($reflector->getFileName());

        $providerParts = explode('\\', AddonServiceProvider::class, -1);
        $namespace = implode('\\', $providerParts);

        $json = json_decode($app['files']->get($directory.'/../composer.json'), true);
        $statamic = $json['extra']['statamic'] ?? [];
        $autoload = $json['autoload']['psr-4'][$namespace.'\\'];

        $app->make(Manifest::class)->manifest = [
            $json['name'] => [
                'id' => $json['name'],
                'slug' => $statamic['slug'] ?? null,
                'version' => 'dev-main',
                'namespace' => $namespace,
                'autoload' => $autoload,
                'provider' => AddonServiceProvider::class,
            ],
        ];
    }
}

<?php

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Statamic\Assets\Asset;
use Statamic\Assets\AssetContainer;
use Statamic\Console\Commands\GlideClear;
use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Stache;
use Statamic\Statamic;

trait DealsWithAssets
{
    public array $assetContainers;

    protected function setUpAssetTest(): void
    {
        // Clean up from old tests
        File::deleteDirectory($this->getTempDirectory());

        $this->setUpTempTestFiles();

        $this->artisan(GlideClear::class);

        $this->createAssetContainer();
    }

    protected function tearDownAssetTest(): void
    {
        $this->initializeDirectory($this->getTempDirectory());
        File::put($this->getTempDirectory('.gitkeep'), '');
        Stache::clear();
    }

    protected function resolveApplicationConfigurationForAssetTest($app)
    {
        $app['config']->set('statamic.assets.image_manipulation.driver', 'gd');
        $app['config']->set('statamic.assets.image_manipulation.secure', false);
    }

    protected function resolveStacheStoresForAssetTest($app)
    {
        $app['config']->set('statamic.stache.stores.collections.directory', $this->getTempDirectory('content/collections'));
        $app['config']->set('statamic.stache.stores.entries.directory', $this->getTempDirectory('content/collections'));
        $app['config']->set('statamic.stache.stores.asset-containers.directory', $this->getTempDirectory('content/assets'));

        Statamic::booted(function () {
            Blueprint::setDirectory($this->getTempDirectory('resources/blueprints'));
        });
    }

    protected function getAssetContainer(mixed $name = null): ?AssetContainer
    {
        if ($name instanceof AssetContainer) {
            $name = $name->handle();
        }

        if (is_string($name)) {
            return $this->assetContainers[$name] ?? $this->getAssetContainer();
        }

        return $this->assetContainers['assets'] ?? null;
    }

    protected function createAssetContainer(string $name = 'assets', array $config = []): AssetContainer
    {
        config(["filesystems.disks.assets_{$name}" => [
            'driver' => 'local',
            'root' => $this->getTempDirectory("assets_{$name}"),
            'url' => "/assets/{$name}",
            ...$config,
        ]]);

        $this->assetContainers[$name] = (new AssetContainer)
            ->handle("test_container_{$name}")
            ->disk("assets_{$name}")
            ->save();

        return $this->assetContainers[$name];
    }

    protected function setUpTempTestFiles()
    {
        $this->initializeDirectory($this->getTempDirectory());
        $this->initializeDirectory($this->getTestFilesDirectory());
        File::copyDirectory(fixtures_path('testfiles'), $this->getTestFilesDirectory());
    }

    protected function initializeDirectory($directory)
    {
        if (File::isDirectory($directory)) {
            File::deleteDirectory($directory);
        }

        File::makeDirectory($directory, 0755, true);
    }

    public function getTempDirectory(...$paths): string
    {
        return fixtures_path('tmp', ...$paths);
    }

    public function getTestFilesDirectory(...$paths): string
    {
        return fixtures_path('tmp', 'testfiles', ...$paths);
    }

    public function getTestFileContents(string $filename): string
    {
        return file_get_contents(fixtures_path("testfiles/{$filename}"));
    }

    public function uploadTestFileToTestContainer(string $path, ?string $filename = null, mixed $container = null): Asset
    {
        $container = $this->getAssetContainer($container);

        $path = $this->getTestFilesDirectory($path);
        $filename ??= basename($path);

        // Duplicate file because in Statamic 3.4 the source asset is deleted after upload
        $duplicate = $this->createFileDuplicate($path);

        $file = new UploadedFile($duplicate, $filename);
        $path = ltrim('/'.$file->getClientOriginalName(), '/');

        return $container->makeAsset($path)->upload($file);
    }

    protected function createFileDuplicate(string $path): string
    {
        $duplicate = preg_replace('/(\.[^.]+)$/', '-'.Carbon::now()->timestamp.'$1', $path);
        File::copy($path, $duplicate);

        return $duplicate;
    }

    protected function makeEmptyAsset(string $path, mixed $container = null): Asset
    {
        $container = $this->getAssetContainer($container);

        return (new Asset)->path($path)->container($container->handle());
    }

    protected function setAssetContainerBlueprint(array $fields, mixed $container = null)
    {
        $container = $this->getAssetContainer($container);

        $container->blueprint()->delete();

        Blueprint::makeFromFields($fields)
            ->setHandle($container->handle())
            ->setNamespace('assets')
            ->save();

        Stache::clear();
        Blink::flush();
    }

    protected function restoreDefaultAssetBlueprint(mixed $container = null)
    {
        $this->setAssetContainerBlueprint([
            'alt' => [
                'type' => 'text',
            ],
        ], $container);
    }
}

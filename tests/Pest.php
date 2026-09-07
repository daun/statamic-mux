<?php

use Daun\StatamicMux\Mux\Enums\ReconciliationState;
use Daun\StatamicMux\Mux\Reconciliation\LocalAssetRecord;
use Daun\StatamicMux\Mux\Reconciliation\ReconciliationPlan;
use Daun\StatamicMux\Mux\Reconciliation\RemoteAssetRecord;
use Daun\StatamicMux\Mux\RemoteVideo;
use MuxPhp\Models\Asset;
use Statamic\Facades\Role;
use Statamic\Facades\User;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class)->in('Feature');

uses(PHPUnit\Framework\TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function fixtures_path(...$paths): string
{
    return join_paths(__DIR__, '__fixtures__', ...$paths);
}

function json_fixture($path): array
{
    return json_decode(file_get_contents(fixtures_path($path)), true);
}

function snapshots_path(...$paths): string
{
    return join_paths(__DIR__, '__snapshots__', ...$paths);
}

function statamic_package_path(...$paths): string
{
    return join_paths(__DIR__, '../vendor/statamic/cms', ...$paths);
}

function userWithMuxPermission(?string $permission): Statamic\Contracts\Auth\User
{
    $user = User::make()->email(uniqid().'@test.com')->password('secret');
    $user->save();

    if ($permission) {
        $role = Role::make('role-'.md5($permission))->addPermission($permission);
        $role->save();
        $user->assignRole($role->handle())->save();
    }

    return $user;
}

if (! function_exists('join_paths')) {
    function join_paths(?string $basePath, ...$paths): string
    {
        foreach ($paths as $index => $path) {
            if (empty($path)) {
                unset($paths[$index]);
            } else {
                $paths[$index] = DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR);
            }
        }

        return $basePath.implode('', $paths);
    }
}

/*
|--------------------------------------------------------------------------
| Reconciliation factories
|--------------------------------------------------------------------------
|
| Command tests drive a stubbed plan rather than a live Mux account. These
| keep the record shape in one place so a signature change lands once.
|
*/

function muxRemoteVideo(string $id, array $attributes = []): RemoteVideo
{
    return RemoteVideo::make(new Asset([
        'id' => $id,
        'status' => 'ready',
        'created_at' => (string) now()->timestamp,
        ...$attributes,
    ]));
}

function muxRemoteRecord(
    string|RemoteVideo $video,
    ReconciliationState $state,
    ?Statamic\Assets\Asset $asset = null,
    array $attributes = [],
): RemoteAssetRecord {
    return new RemoteAssetRecord(
        remote: is_string($video) ? muxRemoteVideo($video) : $video,
        state: $state,
        asset: $asset,
        attributedAssetId: $attributes['attributedAssetId'] ?? $asset?->id(),
        reason: $attributes['reason'] ?? null,
        container: $attributes['container'] ?? $asset?->containerHandle(),
        references: $attributes['references'] ?? collect(),
    );
}

function muxLocalRecord(
    Statamic\Assets\Asset $asset,
    ReconciliationState $state,
    array $attributes = [],
): LocalAssetRecord {
    $selected = $attributes['selected'] ?? null;

    return new LocalAssetRecord(
        asset: $asset,
        state: $state,
        muxId: $attributes['muxId'] ?? null,
        selected: $selected,
        candidates: $attributes['candidates'] ?? ($selected ? collect([$selected]) : collect()),
        reason: $attributes['reason'] ?? null,
        proxySource: $attributes['proxySource'] ?? $state === ReconciliationState::ProxySource,
    );
}

function muxPlan(array $locals = [], array $remotes = [], ?string $container = null): ReconciliationPlan
{
    return new ReconciliationPlan(
        collect($locals),
        collect($remotes),
        $container,
    );
}

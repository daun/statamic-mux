<?php

namespace Daun\StatamicMux\Http\Controllers\Cp;

use Daun\StatamicMux\Support\Queue as MuxQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Statamic\Facades\User;

class CommandController extends Controller
{
    protected const COMMANDS = [
        'mirror' => [
            'command' => 'mux:mirror',
            'dispatched' => 'Mirror queued in the background. Refresh later to see updates.',
            'called' => 'Mirror finished. Refreshing now.',
        ],
        'upload' => [
            'command' => 'mux:upload',
            'dispatched' => 'Upload queued in the background. Refresh later to see updates.',
            'called' => 'Upload finished. Refreshing now.',
        ],
        'prune' => [
            'command' => 'mux:prune',
            'dispatched' => 'Prune queued in the background. Refresh later to see updates.',
            'called' => 'Prune finished. Refreshing now.',
        ],
    ];

    public function run(): JsonResponse
    {
        $user = User::current();
        abort_unless($user && $user->can('manage mux'), 403); // @phpstan-ignore method.notFound

        $command = request()->input('command');
        abort_unless(array_key_exists($command, self::COMMANDS), 404);

        $definition = self::COMMANDS[$command];

        if (MuxQueue::isSync()) {
            $status = 'called';
            Artisan::call($definition['command']);
        } else {
            $status = 'dispatched';
            Artisan::queue($definition['command'])
                ->onConnection(MuxQueue::connection())
                ->onQueue(MuxQueue::queue());
        }

        return response()->json([
            'message' => __($definition[$status]),
            'command' => $command,
            'status' => $status,
        ], 202);
    }
}

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
            'signature' => 'mux:mirror',
            'message' => 'Mirror queued. Uploads and deletions will continue in the background. Refresh later to see updates.',
        ],
        'upload' => [
            'signature' => 'mux:upload',
            'message' => 'Upload queued. Video uploads will continue in the background. Refresh later to see updates.',
        ],
        'prune' => [
            'signature' => 'mux:prune',
            'message' => 'Prune queued. Orphan removals will continue in the background. Refresh later to see updates.',
        ],
    ];

    public function run(): JsonResponse
    {
        $user = User::current();
        abort_unless($user && $user->can('manage mux'), 403); // @phpstan-ignore method.notFound

        $command = request()->input('command');
        abort_unless(array_key_exists($command, self::COMMANDS), 404);

        $definition = self::COMMANDS[$command];

        Artisan::queue($definition['signature'])
            ->onConnection(MuxQueue::connection())
            ->onQueue(MuxQueue::queue());

        return response()->json([
            'message' => __($definition['message']),
            'command' => $command,
        ], 202);
    }
}

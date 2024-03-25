<?php

namespace Daun\StatamicMux\Controllers;

use Illuminate\Support\Facades\File;
use Statamic\CP\Column;
use Statamic\CP\Columns;
use Statamic\Facades\Scope;
use Statamic\Facades\User;

class DashboardController
{
    public function index()
    {
        $user = User::fromUser(auth()->user());

        abort_unless($user->isSuper() || $user->hasPermission('view mux'), 401);

        $columns = Columns::make([
            Column::make('path')->label(__('Asset'))->sortable(true),
            Column::make('status')->label(__('Status'))->sortable(false),
            Column::make('size')->label(__('Size'))->sortable(true),
            Column::make('playtime')->label(__('Playtime'))->sortable(true),
            Column::make('mux_id')->label(__('Mux ID'))->sortable(true),
        ])
            ->setPreferred('mux.assets.columns')
            ->rejectUnlisted()
            ->values();

        return view('mux::index', [
            'columns' => $columns,
            'filters' => Scope::filters('mux-assets'),
        ]);
    }
}

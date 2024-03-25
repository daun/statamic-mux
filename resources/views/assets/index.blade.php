@extends('statamic::layout')

@section('title', Statamic::crumb('Assets', 'Mux'))

{{-- @section('wrapper_class', 'max-w-full') --}}

@section('content')

    <header class="mb-6">
        @include('statamic::partials.breadcrumb', [
            'url' => cp_route('mux.index'),
            'title' => __('Mux')
        ])
        <div class="flex items-center">
            <h1 class="flex-1">
                {{ __('Videos') }}
            </h1>
            @can('create mux')
                <dropdown-list class="mr-2">
                    {{-- <dropdown-item :text="__('Upload All')" redirect="{{ cp_route('mux.assets.upload.all') }}"></dropdown-item> --}}
                </dropdown-list>
                {{-- <a href="{{ cp_route('mux.assets.upload.all') }}" class="btn-primary ml-4">{{ __('Upload All') }}</a> --}}
            @endcan
        </div>
    </header>

    {{ $columns->toJson() }}

    <mux-asset-listing
        :initial-columns="{{ $columns->toJson() }}"
        :filters="{{ $filters->toJson() }}"
        action-url="{{ cp_route('mux.assets.actions.run') }}"
        initial-sort-column="path"
        initial-sort-direction="asc"
    ></mux-asset-listing>

@endsection

@extends('statamic::layout')

@section('title', 'Mux')

@section('content')

    <header class="flex items-center mb-6">
        <h1 class="flex-1">
            Mux
        </h1>

        @can('create mux')
            <dropdown-list class="mr-2">
                {{-- <dropdown-item :text="__('Upload All')" redirect="{{ cp_route('mux.assets.upload.all') }}"></dropdown-item> --}}
            </dropdown-list>
        @endcan

        {{-- <a href="{{ cp_route('mux.assets.upload.all') }}" class="btn-primary ml-4">{{ __('Upload All') }}</a> --}}

    </header>

    {{ $columns->toJson() }}

    <mux-asset-listing
        action-url="{{ cp_route('mux.assets.actions.run') }}"
        initial-sort-column="asset"
        initial-sort-direction="asc"
        :initial-columns="{{ $columns->toJson() }}"
        :filters="{{ $filters->toJson() }}"
    ></mux-asset-listing>

    @include('statamic::partials.docs-callout', [
        'topic' => 'Statamic Mux',
        'url' => 'https://statamic.com/addons/daun/statamic-mux'
    ])

@endsection

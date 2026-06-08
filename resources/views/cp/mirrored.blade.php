@extends('statamic::layout')

@section('content')
    <mux-mirrored-listing
        local-endpoint="{{ $endpoint }}"
        command-endpoint="{{ $commandEndpoint }}"
    />
@endsection

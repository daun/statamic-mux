@extends('statamic::layout')

@section('content')
    <mux-library-listing
        remote-endpoint="{{ $endpoint }}"
        refresh-endpoint="{{ $refreshEndpoint }}"
        dashboard-url="{{ $dashboardUrl }}"
    />
@endsection

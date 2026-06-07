@extends('statamic::layout')

@section('title', $title)

@section('content')
    <mux-video-listing
        title="{{ $title }}"
        listing-page="{{ $listingPage }}"
        local-endpoint="{{ $localEndpoint }}"
        remote-endpoint="{{ $remoteEndpoint }}"
        refresh-endpoint="{{ $refreshEndpoint }}"
        command-endpoint="{{ $commandEndpoint }}"
        dashboard-url="{{ $dashboardUrl }}"
    />
@endsection

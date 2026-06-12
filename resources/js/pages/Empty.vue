<script setup>
import { Link, Head, useArchitecturalBackground } from '@statamic/cms/inertia';
import { Header, Icon, EmptyStateMenu, EmptyStateItem, DocsCallout } from '@statamic/cms/ui';

useArchitecturalBackground();
</script>

<template>
    <Head :title="__('Mux')" />

    <header class="py-8 mt-8 text-center starting-style-transition" v-cloak>
        <h1 class="text-[25px] font-medium antialiased flex justify-center items-center gap-2 sm:gap-3">
            <ui-icon name="mux::video-player" class="size-5 text-gray-500" />
            {{ __('Mux Videos') }}
        </h1>
    </header>

    <EmptyStateMenu :heading="__('Mux is currently not configured for this site.')">
        <EmptyStateItem
            href="https://statamic-mux.daun.ltd/connecting-mux"
            icon="mux::connect"
            :heading="__('Connect to Mux')"
            :description="__('Register a Mux account and connect credentials.')"
        />
        <EmptyStateItem
            href="https://statamic-mux.daun.ltd/upload"
            icon="sync"
            :heading="__('Set Up Automatic Sync')"
            :description="__('Configure the addon for mirroring local videos.')"
        />
        <EmptyStateItem
            href="https://statamic-mux.daun.ltd/display"
            icon="mux::video-live"
            :heading="__('Begin Streaming Videos')"
            :description="__('Use web components to render a video player.')"
        />
    </EmptyStateMenu>

    <DocsCallout :topic="__('Statamic Mux')" url="https://statamic-mux.daun.ltd" />
</template>

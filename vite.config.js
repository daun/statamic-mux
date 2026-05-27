import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import statamic from '@statamic/cms/vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        statamic(),
        tailwindcss(),
        laravel({
            input: [
                'resources/css/addon.css',
                'resources/js/addon.js',
            ],
            publicDirectory: 'resources/dist',
            refresh: true,
        }),
    ],
});

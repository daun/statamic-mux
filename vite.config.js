import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue2';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/addon.css',
                'resources/js/addon.js',
            ],
            publicDirectory: 'resources/dist',
            refresh: true,
        }),
        vue(),
    ],
});

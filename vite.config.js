import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/docs-api.js',
                'resources/js/pages/dashboard.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        // Pre-process CSS on dev server startup to avoid FOUC on first request
        warmup: {
            clientFiles: ['./resources/css/app.css', './resources/js/app.js'],
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

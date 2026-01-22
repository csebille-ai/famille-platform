import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    build: {
        target: 'es2017',
    },
    server: {
        // In Docker, Vite must listen on 0.0.0.0 to be reachable from the host via port mapping.
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: {
            host: '127.0.0.1',
            port: 5173,
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/chat-page.js'],
            refresh: true,
        }),
    ],
});

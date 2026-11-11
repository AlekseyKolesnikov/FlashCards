import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig(({ command }) => ({
    // Built files refer to each other by relative URLs, so the app works from any
    // subfolder without rebuilding (SPECIFICATION.md §10.2). Blade's @vite adds the folder.
    base: command === 'build' ? './' : undefined,
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/main.tsx'],
            refresh: true,
        }),
        react(),
    ],
}));

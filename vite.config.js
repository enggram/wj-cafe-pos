import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        VitePWA({
            registerType: 'autoUpdate',
            strategies: 'generateSW',
            manifest: {
                name: 'WhiteJersey Cafe POS',
                short_name: 'WJ Cafe',
                description: 'Point-of-sale system for WhiteJersey Cafe',
                theme_color: '#0a0a0a',
                background_color: '#0a0a0a',
                display: 'standalone',
                scope: '/',
                start_url: '/',
                icons: [
                    {
                        src: '/icons/icon-192x192.svg',
                        sizes: '192x192',
                        type: 'image/svg+xml',
                        purpose: 'any',
                    },
                    {
                        src: '/icons/icon-512x512.svg',
                        sizes: '512x512',
                        type: 'image/svg+xml',
                        purpose: 'any',
                    },
                    {
                        src: '/icons/icon-512x512.svg',
                        sizes: '512x512',
                        type: 'image/svg+xml',
                        purpose: 'maskable',
                    },
                ],
            },
            workbox: {
                globPatterns: ['**/*.{js,css,html,svg,png,ico,woff,woff2}'],
                navigateFallback: '/offline.html',
                navigateFallbackAllowlist: [/^\/(?!api\/).*/],
                runtimeCaching: [
                    {
                        urlPattern: /^https:\/\/fonts\.googleapis\.com\/.*/i,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'google-fonts-cache',
                            expiration: {
                                maxEntries: 10,
                                maxAgeSeconds: 60 * 60 * 24 * 365, // 1 year
                            },
                            cacheableResponse: {
                                statuses: [0, 200],
                            },
                        },
                    },
                    {
                        urlPattern: /^https:\/\/fonts\.gstatic\.com\/.*/i,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'gstatic-fonts-cache',
                            expiration: {
                                maxEntries: 10,
                                maxAgeSeconds: 60 * 60 * 24 * 365, // 1 year
                            },
                            cacheableResponse: {
                                statuses: [0, 200],
                            },
                        },
                    },
                ],
            },
            devOptions: {
                enabled: false,
            },
        }),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});

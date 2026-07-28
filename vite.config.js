import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const appUrl = env.APP_URL ? new URL(env.APP_URL) : null;
    const devServerPort = Number(env.VITE_PORT || 5173);
    const hmrHost = env.VITE_HMR_HOST || appUrl?.hostname || 'localhost';
    const hmrProtocol = env.VITE_HMR_PROTOCOL || (appUrl?.protocol === 'https:' ? 'wss' : 'ws');
    const hmrClientPort = Number(env.VITE_HMR_CLIENT_PORT || env.VITE_HMR_PORT || devServerPort);
    const devServerOrigin = env.VITE_DEV_SERVER_URL || `${appUrl?.protocol || 'http:'}//${hmrHost}:${devServerPort}`;

    return {
        plugins: [
            react(),
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/js/app.js',
                    'resources/js/spa/main.jsx',
                ],
                refresh: true,
            }),
            tailwindcss(),
        ],
        server: {
            host: '0.0.0.0',
            port: devServerPort,
            strictPort: true,
            origin: devServerOrigin,
            hmr: {
                host: hmrHost,
                protocol: hmrProtocol,
                clientPort: hmrClientPort,
            },
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});

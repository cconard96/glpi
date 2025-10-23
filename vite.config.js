import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
    build: {
        outDir: 'public/build/vite',
        lib: {
            entry: ['js/src/vue/app.js'],
            name: 'vue',
            fileName: (format, entryName) => `${entryName}.${format}.js`,
            cssFileName: (format, entryName) => `${entryName}.css`,
        }
    },
    define: {
        'process.env': {
            __VUE_OPTIONS_API__: false, // We will only use composition API
            __VUE_PROD_DEVTOOLS__: false,
            __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: false,
            NODE_ENV: 'production',
        },
    },
    publicDir: false,
    plugins: [
        vue(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./js/src', import.meta.url))
        },
    },
});

import {defineConfig} from 'vite';
import vue from '@vitejs/plugin-vue';
import { dynamicBase } from 'vite-plugin-dynamic-base';

export default defineConfig(({ mode }) => {
    return {
        base: '/__dynamic_base__/',
        build: {
            rolldownOptions: {
                input: 'js/src/vue/app.js',
                platform: 'browser',
                output: {
                    format: 'esm',
                    dir: 'public/build/vue',
                    entryFileNames: '[name].js',
                    chunkFileNames: 'vue-sfc/[name]-[hash].js',
                    assetFileNames: 'vue-sfc/[name]-[hash][extname]',
                }
            },
        },
        define: {
            'process.env.NODE_ENV': mode === 'production' ? '"production"' : '"development"',
            '__VUE_OPTIONS_API__': true,
        },
        publicDir: false,
        plugins: [
            vue(),
            dynamicBase({
                publicPath: '"./" + CFG_GLPI.root_doc + "/build/vue/"',
            }),
        ],
    };
});

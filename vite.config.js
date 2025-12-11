import {defineConfig} from 'vite';
import vue from '@vitejs/plugin-vue';
import {glob} from "glob";

export default defineConfig(({ mode }) => {
    return {
        build: {
            rolldownOptions: {
                // use all JS files in lib/bundles as entrypoints
                input: glob.sync('lib/bundles/*.js'),
                platform: 'browser',
                output: {
                    format: 'esm',
                    dir: 'public/lib',
                    entryFileNames: '[name].js',
                }
            },
        },
        define: {
            'process.env.NODE_ENV': mode === 'production' ? '"production"' : '"development"',
        },
        publicDir: false,
        plugins: [
            vue(),
        ],
        css: {
            lightningcss: {
                errorRecovery: true,
            }
        }
    };
});

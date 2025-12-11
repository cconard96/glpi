import { defineConfig, normalizePath } from 'vite';
import vue from '@vitejs/plugin-vue';
import { glob } from "glob";
import { viteStaticCopy } from "vite-plugin-static-copy";
import path from "node:path";
import commonjs from 'vite-plugin-commonjs';
import postcssRTLCSS from "postcss-rtlcss";

export default defineConfig(({ mode }) => {
    return {
        build: {
            rolldownOptions: {
                // use all JS files in lib/bundles as entrypoints
                input: glob.sync('lib/bundles/*.js'),
                platform: 'browser',
                output: {
                    format: 'esm',
                    //polyfillRequire: true,
                    dir: 'public/lib',
                    entryFileNames: '[name].js',
                    cssEntryFileNames: '[name].css',
                    assetFileNames: '[name].[ext]',
                }
            },
        },
        define: {
            'process.env.NODE_ENV': mode === 'production' ? '"production"' : '"development"',
        },
        publicDir: false,
        plugins: [
            commonjs(),
            vue(),
            viteStaticCopy({
                targets: [
                    {
                        src: normalizePath(path.resolve(__dirname, 'node_modules/@glpi-project/illustrations/dist/*.{json,svg}')),
                        dest: normalizePath(path.resolve(__dirname, 'public/lib/glpi-project/illustrations'))
                    }
                ]
            }),
        ],
        css: {
            lightningcss: {
                errorRecovery: true,
            },
            postcss: {
                plugins: [postcssRTLCSS()]
            }
        }
    };
});

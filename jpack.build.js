import postcss from "rollup-plugin-postcss";
import url from "@rollup/plugin-url";
import json from "@rollup/plugin-json";
import resolve from "@rollup/plugin-node-resolve";
import commonjs from "@rollup/plugin-commonjs";
import terser from '@rollup/plugin-terser';

import {
    jPackConfig,
    cleanBuildFolder,
    generateBuildJs,
    generateWrappedJs,
    onPacked
} from "./jpack.utils.js";

export async function rollupPlugins(config) {
    return [
        {
            name: 'beforeBuild',
            buildStart() {
                cleanBuildFolder(config.assetsPath, true);
            },
        },

        {
            name: 'preprocess',
            buildStart() {
                generateBuildJs(config, (code) => {
                    const genJs = jPackConfig.get('genBuildJs');
                    if (typeof genJs === 'function') {
                        return genJs(code, config);
                    }
                    return code;
                });
            },
        },

        postcss({
            extract: jPackConfig.get('alias') + '.min.css', // Save CSS to dist/css/
            minimize: true, // Minify CSS
            sourceMap: false, // Disable source map for CSS
            extensions: ['.less'], // Process LESS files
        }),

        url({
            include: ['**/*.woff', '**/*.woff2'],
            limit: 0,
            emitFiles: true,
            fileName: '[name][extname]',
            destDir: config.assetsPath + '/fonts/',
        }),

        url({
            include: ['**/*.png'],
            limit: 0,
            emitFiles: true,
            fileName: '[name][extname]',
            destDir: config.assetsPath + '/images/',
        }),

        json(), // Handle JSON imports
        resolve(), // Resolve Node.js modules
        commonjs(), // Convert CommonJS modules to ES6

        {
            name: 'wrap',
            renderChunk(code) {
                return generateWrappedJs(code, config);
            },
        },

        terser(),

        {
            name: 'jpacked',
            writeBundle() {
                onPacked(config);
            },
        }
    ];
}

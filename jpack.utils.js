import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';
import { rollup } from 'rollup';
import { fileURLToPath } from 'url';
import { rollupPlugins } from './jpack.build.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const basePath = path.resolve(__dirname);

export const LogMe = {
    doLog: (method, ...message) => {
        if (!process.env.DEBUG) {
            return;
        }
        console[method](...message);
    },
    log: (...message) => {
        LogMe.doLog('log', ...message);
    },
    warn: (...message) => {
        LogMe.doLog('warn', ...message);
    },
    error: (...message) => {
        LogMe.doLog('error', ...message);
    },
    debug: (...message) => {
        LogMe.doLog('debug', ...message);
    },
    dir: (...message) => {
        LogMe.doLog('dir', ...message);
    },
};

const config = {};

const jPackConfig = {
    init: (cfg) => {
        Object.assign(config, cfg);

        if (!config.name) {
            throw new Error('Invalid configuration: name is required.');
        }

        if (!config.alias) {
            config.alias = config.name.toLowerCase();
        }

        if (!config.cfg) {
            config.cfg = config.alias;
        }
    },
    get(key, def) {
        return config[key] || def;
    },
    set(key, value) {
        config[key] = value;
    }
};

export { jPackConfig };

export function parseCommandLine({
    target,
    zip,
    name = null,
    configFileName
}) {
    let cfgPath = null;
    let properties = {};

    if (target) {
        console.log(`Using target path: ${target}`);

        properties.buildTarget = target;
        properties.buildZip = zip;
        properties.buildName = 'export';

        if (!fs.existsSync(target)) {
            console.error(`Target path does not exist: ${target}`);
            process.exit(1);
        }

        if (!fs.lstatSync(target).isDirectory()) {
            console.error(`Target path is not a directory: ${target}`);
            process.exit(1);
        }

        if (!fs.existsSync(path.join(target, configFileName))) {
            console.error(`Configuration file not found in target path: ${target}`);
            process.exit(1);
        }

        cfgPath = path.join(target, configFileName);

        const files = fs.readdirSync(target)
            .filter(file => file !== configFileName);

        if (files.length > 0) {
            console.error('Error: Build target should only contain ' + configFileName);
            process.exit(1);
        }
    }
    else {
        if (zip) {
            properties.buildZip = true;
            properties.buildName = name || 'zip';
            if (properties.buildName === 'default') {
                properties.buildName = 'zip';
            }
        }
        else {
            properties.buildName = name || 'default';
            properties.buildZip = false;
        }
    }

    if (!cfgPath || !fs.existsSync(cfgPath)) {
        LogMe.warn('Using default config');
        cfgPath = path.join(basePath, configFileName);
    }

    return { cfgPath, properties };
};

export async function doBuild(config) {
    LogMe.dir(config);

    const inputOptions = {
        input: config.buildJsFilePath,
        plugins: rollupPlugins(config),
    };

    const outputOptions = {
        file: config.assetsPath + '/js/' + jPackConfig.get('alias') + '.min.js',
        format: "iife",
        name: jPackConfig.get('name'),
        sourcemap: false,
    };

    try {
        LogMe.log('Starting Rollup build...');
        const bundle = await rollup(inputOptions);
        await bundle.write(outputOptions);
        LogMe.log('Rollup build completed successfully.');
    } catch (error) {
        console.error('Error during Rollup build:', error);
        process.exit(1);
    }
};

export async function getPackageConfig(cfgPath, properties, onGetConfig) {
    if (!fs.existsSync(cfgPath)) {
        // If the config file is not found, use the default path
        LogMe.warn(`Configuration file not found: ${cfgPath}`);
        LogMe.warn(`Attempting to use default configuration file: ${path.join(basePath, jPackConfig.get('cfg') + '.config.json')}`);

        cfgPath = path.join(basePath, jPackConfig.get('cfg') + '.config.json');
        if (!fs.existsSync(cfgPath)) {
            throw new Error(`Configuration file not found: ${cfgPath}`);
        }
    }

    let config = JSON.parse(fs.readFileSync(cfgPath, 'utf8'));

    if (properties.buildZip) {
        config.buildZip = true;
    }

    // process config from the command line
    config = onGetConfig(config, properties);

    const pkgPath = path.join(basePath, 'package.json');
    const pckg = fs.readFileSync(pkgPath, 'utf8');
    config.version = JSON.parse(pckg).version;
    // buildTarget specified in the command line will override the one in the config file
    if (properties.buildTarget) {
        config.buildTarget = properties.buildTarget;
    }

    if (config.buildTarget) {
        config.buildName = 'export';
    }
    else {
        // buildName specified in the command line will override the one in the config file
        if (properties.buildName) {
            config.buildName = properties.buildName;
        }
        config.buildName = config.buildName.replace(/[^a-zA-Z0-9]/g, ''); // Sanitize the build folder name

        if (!config.buildName) {
            config.buildName = 'default';
        }
    }

    if (config.buildName === 'default') {
        config.assetsPath = 'dist';
        config.importPrefix = '../';
    }
    else {
        config.assetsPath = 'build/' + config.buildName;
        config.importPrefix = '../../';
    }

    config.basePath = basePath;
    config.assetsFullpath = path.join(basePath, config.assetsPath);
    config.buildJsFilePath = path.join(basePath, config.assetsPath + '/build.js');
    config.buildTemplatePath = path.join(basePath, 'jpack.template');
    config.wrapperPath = path.join(basePath, 'jpack.wrapper.js');

    return config;
};

export function cleanBuildFolder(buildFolderPath, create = false) {
    if (fs.existsSync(buildFolderPath)) {
        fs.readdirSync(buildFolderPath).forEach(file => {
            const filePath = path.join(buildFolderPath, file);
            if (fs.lstatSync(filePath).isDirectory()) {
                fs.rmSync(filePath, { recursive: true });
                LogMe.log('- Removed directory: ' + filePath);
            }
            else {
                fs.unlinkSync(filePath);
                LogMe.log('- Removed file: ' + filePath);
            }
        });
    } else if (create) {
        fs.mkdirSync(buildFolderPath, { recursive: true });
        LogMe.log('- Created directory: ' + buildFolderPath);
    }
};

export function generateBuildJs(config, onGenerateBuildJs = null) {
    LogMe.log('Generate the build.js file ...');

    // Read the build template
    let code = fs.readFileSync(config.buildTemplatePath, 'utf8');

    // append code to the template
    code = onGenerateBuildJs(code, config);

    code = code.replace(/{{PREFIX}}/g, config.importPrefix);

    fs.writeFileSync(config.buildJsFilePath, code, 'utf8');

    if (fs.existsSync(config.buildJsFilePath)) {
        LogMe.log('Generated build successfully in "' + config.buildJsFilePath + '"');
    } else {
        console.error('Error: Generated build file not found: ' + config.buildJsFilePath);
        process.exit(1);
    }
};

export function generateWrappedJs(code, config, onGenerateWrappedJs = null) {
    LogMe.log('Generate the wrapper.js file ...');

    const date = new Date();
    const wrapper = fs.readFileSync(config.wrapperPath, 'utf8');
    let wrapped = wrapper.replace('// @CODE', code)
        .replace(/@VERSION/g, config.version)
        .replace(/@BUNDLE/g, config.buildName)
        .replace(/@DATE/g, date.toISOString().replace(/:\d+\.\d+Z$/, "Z"));

    if (typeof onGenerateWrappedJs === 'function') {
        wrapped = onGenerateWrappedJs(wrapped, config);
    }

    return wrapped;
}

export function onPacked(config) {
    LogMe.log('Build completed successfully.');

    moveCssFiles(config);

    const onPacked = jPackConfig.get('onPacked');
    if (typeof onPacked === 'function') {
        onPacked(config);
    }

    // Cleanup the build folder
    cleanupBuild(config);

    LogMe.log('Build process completed.');
};

function moveCssFiles(config) {
    LogMe.log('Moving CSS files ...');

    fs.readdirSync(config.assetsPath + '/js')
        .filter(file => file.endsWith('.css'))
        .forEach(file => {
            const oldPath = path.join(config.assetsPath + '/js', file);
            const newPath = path.join(config.assetsPath + '/css', file);
            fs.mkdirSync(path.dirname(newPath), { recursive: true });
            fs.renameSync(oldPath, newPath);
            LogMe.log('- ' + file);
        });
}

function cleanupBuild(config) {
    LogMe.log('Cleaning up generated build ...');

    // Remove the generated build.js file
    if (fs.existsSync(config.buildJsFilePath)) {
        LogMe.log('Removed generated build.js file');
        fs.unlinkSync(config.buildJsFilePath);
    }

    let emptyBuildFolder = false;
    const ignoreRemove = [];

    if (config.buildZip) {
        LogMe.log('Creating zip file ...');
        const zipFilePath = path.join(config.assetsFullpath, config.buildName + '.zip');

        execSync(`cd ${config.assetsFullpath} && zip -r ${zipFilePath} .`, { stdio: 'inherit' });
        LogMe.log('-> ' + zipFilePath);

        if (config.buildTarget) {
            LogMe.log('Transfer zip file ...');
            const zipTargetPath = path.join(config.buildTarget, config.buildName + '.zip');
            fs.copyFileSync(zipFilePath, zipTargetPath);
            LogMe.log('-> ' + zipTargetPath);
        }

        emptyBuildFolder = true;
        ignoreRemove.push(zipFilePath);
    }
    else if (config.buildTarget) {
        LogMe.log('Copy generated build folder ...');

        fs.mkdirSync(config.buildTarget, { recursive: true });

        // Iterate over the files and directories in config.assetsPath
        fs.readdirSync(config.assetsPath).forEach(file => {
            const sourcePath = path.join(config.assetsPath, file);
            const destinationPath = path.join(config.buildTarget, file);

            if (fs.lstatSync(sourcePath).isDirectory()) {
                // Recursively copy directories
                fs.mkdirSync(destinationPath, { recursive: true });
                fs.readdirSync(sourcePath).forEach(subFile => {
                    const subSourcePath = path.join(sourcePath, subFile);
                    const subDestinationPath = path.join(destinationPath, subFile);
                    fs.copyFileSync(subSourcePath, subDestinationPath);
                    LogMe.log('- Copied file: ' + subDestinationPath);
                });
            } else {
                // Copy files
                fs.copyFileSync(sourcePath, destinationPath);
                LogMe.log('- Copied file: ' + destinationPath);
            }
        });

        emptyBuildFolder = true;
    }

    LogMe.log('Cleaning up build folder ...');

    if (emptyBuildFolder) {
        fs.readdirSync(config.assetsPath).forEach(file => {
            const filePath = path.join(config.assetsPath, file);
            if (fs.lstatSync(filePath).isDirectory()) {
                fs.rmSync(filePath, { recursive: true, force: true });
                LogMe.log('Removed folder: ' + filePath);
            }
            else {
                if (filePath.endsWith('.zip')) {
                    return;
                }

                fs.unlinkSync(filePath);
                LogMe.log('Removed file: ' + filePath);
            }
        });
    }
    else {
        fs.readdirSync(config.assetsPath).forEach(file => {
            const filePath = path.join(config.assetsPath, file);
            if (fs.lstatSync(filePath).isDirectory()) {
                if (fs.readdirSync(filePath).length === 0) {
                    fs.rmdirSync(filePath);
                    LogMe.log('Removed empty folder: ' + filePath);
                }
            }
        });
    }
}

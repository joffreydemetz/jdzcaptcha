import {
    jPackConfig,
    getPackageConfig,
    parseCommandLine,
    doBuild
} from "../jpack.utils.js";

export async function build({
    target,
    zip,
    name,
} = {}) {
    const configFileName = jPackConfig.get('cfg') + '.config.json';

    const { cfgPath, properties } = parseCommandLine({
        target,
        zip,
        name,
        configFileName
    });

    console.log(`Load config from file: ${cfgPath}`);

    try {
        let config = await getPackageConfig(cfgPath, properties, (config, properties) => {
            if (properties.defaults) {
                config.defaults = properties.defaults;
            }
            return config;
        });

        const check = jPackConfig.get('checkConfig') ?? ((config) => config);
        config = check(config);

        await doBuild(config);
    } catch (error) {
        console.error(`Failed to load configuration file: ${cfgPath}`);
        console.error(error);
        process.exit(1);
    }
}


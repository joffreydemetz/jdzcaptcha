import "../jpack.js";
import yargs from "yargs/yargs";
import { build } from "./build.js";
import { jPackConfig } from "../jpack.utils.js";

const argv = yargs(process.argv.slice(2))
    .version(false)
    .usage('Usage: $0 [options]')
    .example('$0 --config ./' + jPackConfig.get('cfg') + '.config.json', 'Build a ' + jPackConfig.get('name') + ' bundle with a custom config file')
    .command({
        command: "[options]",
        describe: "Build a '" + jPackConfig.get('name') + "' bundle"
    })
    .option('target', {
        alias: 't',
        type: 'string',
        description: 'Target path',
        default: '',
    })
    .option('zip', {
        alias: 'z',
        type: 'boolean',
        description: 'Create a zip file',
        default: false,
    })
    .option('name', {
        alias: 'n',
        type: 'string',
        description: 'Set the build name',
        default: '',
    })
    .help()
    .alias('help', 'h')
    .argv;

build(argv);
import { jPackConfig } from "./jpack.utils.js";
import fs from 'fs';
import path from 'path';

function listIcons(theme, variant, iconsPath) {
    const themePath = path.join(iconsPath, theme);
    const variantPath = path.join(themePath, variant);

    if (!fs.existsSync(variantPath)) {
        console.error(`Variant path does not exist: ${variantPath}`);
        return [];
    }

    const files = fs.readdirSync(variantPath);

    const icons = [];

    files
        .filter((file) => path.extname(file).toLowerCase() === '.png') // Filter .png files
        .map((file) => icons.push({
            theme,
            variant,
            name: path.basename(file, '.png'),
        }));

    icons.sort((a, b) => {
        return a.name.localeCompare(b.name, undefined, { numeric: true, sensitivity: 'base' });
    });

    return icons;
}

jPackConfig.init({
    name: 'JdzCaptha',
    alias: 'jdzcaptcha',
    cfg: 'jdzcaptcha',
    checkConfig: (config) => {
        config.iconsPath = path.join(config.basePath, 'lib', 'iconsets');
        config.icons = listIcons(config.theme, config.variant, config.iconsPath);
        return config;
    },
    genBuildJs: (code, config) => {
        // Generate imports for iconsets
        const iconImports = config.icons
            .map((icon) => `import '${config.importPrefix}lib/iconsets/${icon.theme}/${icon.variant}/${icon.name}.png';`)
            .join('\n');

        return code
            .replace(/{{ICONS}}/g, iconImports);
    },
    onPacked: (config) => {
        // create the icons directory if it doesn't exist
        if (!fs.existsSync(path.join(config.assetsFullpath, 'icons'))) {
            fs.mkdirSync(path.join(config.assetsFullpath, 'icons'), { recursive: true });
        }

        // move all files except placeholder.png to the icons/ folder
        const imagesDir = path.join(config.assetsFullpath, 'images');

        fs.readdirSync(imagesDir).forEach(file => {
            if (file !== 'placeholder.png') {
                fs.renameSync(
                    path.join(imagesDir, file),
                    path.join(config.assetsFullpath, 'icons', file)
                );
            }
        });
    },
});

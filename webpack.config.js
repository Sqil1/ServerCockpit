const Encore = require('@symfony/webpack-encore');

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

// Définir les variables d'environnement
const isDev = !Encore.isProduction();
const apiBasePath = isDev ? '/ServerCockpit/public' : '';

Encore
    .setOutputPath('public/build/')
    .setPublicPath(isDev ? '/ServerCockpit/public/build' : '/build')
    .copyFiles({
        from: './assets/styles/images',
    })
    .addEntry('app', './assets/app.js')

    // Définir des variables globales
    .configureDefinePlugin((options) => {
        options.API_BASE_PATH = JSON.stringify(apiBasePath);
    })

    .splitEntryChunks()
    .enableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())
    .enableSassLoader();

module.exports = Encore.getWebpackConfig();
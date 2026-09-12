const { merge } = require("webpack-merge");
const BrowserSyncPlugin = require("browser-sync-webpack-plugin");
const common = require("./webpack.common.js");

module.exports = merge(common, {
  mode: "development",
  // "eval-*" devtool values only apply to JS; CSS source maps come from the
  // sourceMap options on the loaders in webpack.common.js.
  devtool: "eval-cheap-module-source-map",
  plugins: [
    new BrowserSyncPlugin({
      host: "localhost",
      port: 3000,
      proxy: "https://drupal-blade.ddev.site",
      files: [
        "./web/themes/custom/proton_systems/**/*.html.twig",
        "./web/themes/custom/proton_systems/**/*.theme",
        "./web/themes/custom/proton_systems/assets/dist/*.css",
      ],
      reloadDelay: 0,
    }),
  ],
});

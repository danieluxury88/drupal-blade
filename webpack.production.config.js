const { merge } = require("webpack-merge");
const CssMinimizerPlugin = require("css-minimizer-webpack-plugin");
const TerserPlugin = require("terser-webpack-plugin");
const common = require("./webpack.common.js");

module.exports = merge(common, {
  mode: "production",
  optimization: {
    minimizer: [
      new TerserPlugin({
        terserOptions: {
          compress: {
            drop_console: true,
            drop_debugger: true,
          },
        },
      }),
      new CssMinimizerPlugin(),
    ],
    // Keep every entry self-contained. Drupal loads JS by explicit filename via
    // proton_systems.libraries.yml, so any extra chunk webpack emits (a vendor
    // split, a dynamic import) would never be requested and its code would go
    // missing in production only. If splitting is ever worth it, add the
    // resulting filenames to libraries.yml in the same change.
    splitChunks: false,
  },
});

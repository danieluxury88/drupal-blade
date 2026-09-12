const path = require("path");
const MiniCssExtractPlugin = require("mini-css-extract-plugin");

const distPath = "./web/themes/custom/proton_systems/assets/dist";

module.exports = {
  entry: {
    app: "./web/themes/custom/proton_systems/assets/app.js",
    ckeditor: "./web/themes/custom/proton_systems/assets/ckeditor.js",
  },
  output: {
    path: path.resolve(__dirname, distPath),
    filename: "[name].bundle.js",
    publicPath: "/themes/custom/proton_systems/assets/dist/",
    clean: true,
  },
  externals: {
    Drupal: "Drupal",
  },
  cache: {
    type: "filesystem",
    buildDependencies: {
      config: [__filename],
    },
  },
  module: {
    rules: [
      // Drupal reads CSS from disk (libraries.yml, ckeditor5-stylesheets), so
      // both dev and production must extract real files. Never style-loader.
      {
        test: /\.scss$/,
        use: [
          MiniCssExtractPlugin.loader,
          {
            loader: "css-loader",
            options: { sourceMap: true, importLoaders: 2 },
          },
          {
            loader: "postcss-loader",
            options: { sourceMap: true },
          },
          {
            loader: "sass-loader",
            options: {
              sourceMap: true,
              sassOptions: { charset: false },
            },
          },
        ],
      },
      {
        test: /\.css$/,
        use: [
          MiniCssExtractPlugin.loader,
          {
            loader: "css-loader",
            options: { sourceMap: true, importLoaders: 1 },
          },
          {
            loader: "postcss-loader",
            options: { sourceMap: true },
          },
        ],
      },
      {
        test: /\.(png|svg|jpg|jpeg|gif)$/i,
        type: "asset",
        parser: {
          dataUrlCondition: {
            maxSize: 4 * 1024,
          },
        },
        generator: {
          filename: "images/[name].[hash:8][ext]",
        },
      },
      {
        test: /\.(woff|woff2|eot|ttf|otf)$/i,
        type: "asset",
        parser: {
          dataUrlCondition: {
            maxSize: 4 * 1024,
          },
        },
        generator: {
          filename: "fonts/[name].[hash:8][ext]",
        },
      },
    ],
  },
  plugins: [
    new MiniCssExtractPlugin({
      filename: "[name].style.css",
    }),
  ],
};

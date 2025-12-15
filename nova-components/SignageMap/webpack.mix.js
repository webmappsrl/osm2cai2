let mix = require('laravel-mix')
const path = require('path')

mix
  .setPublicPath('dist')
  .js('resources/js/field.js', 'js')
  .vue({ version: 3 })
  .css('resources/css/field.css', 'css')
  .webpackConfig({
    externals: {
      vue: 'Vue'
    },
    resolve: {
      extensions: ['.js', '.jsx', '.vue', '.json'],
      alias: {
        'ol': path.resolve(__dirname, 'node_modules/ol')
      }
    }
  })





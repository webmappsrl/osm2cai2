let mix = require('laravel-mix')

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
      extensions: ['.js', '.jsx', '.vue', '.json']
    }
  })



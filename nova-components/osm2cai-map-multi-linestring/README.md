# osm2cai-map-multi-linestring
![OSM2CAI Map Multi Linestring, awesome resource field for Nova](banner.jpg)

---

- [osm2cai-map-multi-linestring](#osm2cai-map-multi-linestring)
  - [Requirements](#requirements)
  - [Installation & Development](#installation--development)
  - [Development Commands](#development-commands)
  - [Usage](#usage)
    - [OSM2CAI Map Multi Linestring](#osm2cai-map-multi-linestring-1)
  - [Configuration](#configuration)

## Requirements

- `php: ^8`
- `laravel/nova: ^4`

## Installation & Development

This package is included in the main project. No additional installation steps are required - it will be automatically set up when you run:

```bash
composer install
```

If you need to develop or modify the package, you can find it in the `nova-components/osm2cai-map-multi-linestring` directory of your project.

For development, you might need to install the package dependencies:
```bash
cd nova-components/osm2cai-map-multi-linestring && npm install
```

## Development Commands

Here are all the available commands for developing and maintaining the component:

### Nova Commands
```bash
# Install Nova in your Laravel project
composer require laravel/nova

# Run Nova's installation process (publishes config, sets up database, etc.)
php artisan nova:install
```

### Package Development
From the package directory (`nova-components/osm2cai-map-multi-linestring`):
```bash
# Install all JavaScript dependencies
npm install

# Watch for changes during development
# This will automatically recompile your assets when you make changes to your JavaScript or CSS files
npm run dev

# Compile and minify for production
# Use this when you're ready to deploy your changes
npm run prod

# Combination of prod and publishing
# This will build your assets and publish them to the public directory
npm run build

# Update PHP dependencies
composer update
```

### Laravel Mix Commands
```bash
# Run all Mix tasks
npm run watch

# Run all Mix tasks and minify output
npm run production

# Compile assets without watching
npm run development
```

These commands are essential for development workflow.

## Usage

### OSM2CAI Map Multi Linestring

![image](field.png)

You can display a PostGIS geography(MultiLineString,4326) area on the map and change it by uploading a new MultiLineString file (.GPX, .KML, .GEOJSON).
To use the Map Multi Linestring feature, include the Osm2caiMapMultiLinestring class and add it to your resource's fields. Customize the map settings by providing metadata such as the initial map center, tile server URL, attribution text, minimum and maximum zoom levels, and default zoom level. The component will display your tracks with different styles based on their order in the MultiLineString collection.

```php
use Wm\Osm2caiMapMultiLinestring\Osm2caiMapMultiLinestring;

/**
 * Get the fields displayed by the resource.
 *
 * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
 * @return array
 */
public function fields(NovaRequest $request)
{
    return [
        ID::make()->sortable(),
            ...
        Osm2caiMapMultiLinestring::make('geometry')->withMeta([
            'center' => [42, 10],
            'attribution' => '<a href="https://webmapp.it/">Webmapp</a> contributors',
            'tiles' => 'https://api.webmapp.it/tiles/{z}/{x}/{y}.png',
            'minZoom' => 5,
            'maxZoom' => 17,
            'defaultZoom' => 10
        ]),
    ];
}
```

## Configuration

<div style="overflow-x:auto;">
  <table style="width: 100%">
    <thead>
      <tr>
        <th>Property</th>
        <th>Type</th>
        <th style="width: 10%;">Default</th>
        <th>Description</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>center</td>
        <td>array</td>
        <td>[0,0]</td>
        <td>The coordinates used to center the view of an empty map.</td>
      </tr>
      <tr>
        <td>attribution</td>
        <td>string</td>
        <td>'&lt;a href="https://www.openstreetmap.org/"&gt;OpenStreetMap&lt;/a&gt; contributors'</td>
        <td>The HTML content displayed as map attribution.</td>
      </tr>
      <tr>
        <td>tiles</td>
        <td>string</td>
        <td>'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'</td>
        <td>The tile URL used for the map.</td>
      </tr>
      <tr>
        <td>minZoom</td>
        <td>integer</td>
        <td>5</td>
        <td>The minimum zoom level allowed on the map.</td>
      </tr>
      <tr>
        <td>maxZoom</td>
        <td>integer</td>
        <td>17</td>
        <td>The maximum zoom level allowed on the map.</td>
      </tr>
      <tr>
        <td>defaultZoom</td>
        <td>integer</td>
        <td>8</td>
        <td>The initial zoom level when the map is first displayed.</td>
      </tr>
    </tbody>
  </table>
</div>




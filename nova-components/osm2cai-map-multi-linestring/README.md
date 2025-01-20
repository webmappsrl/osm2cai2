# map-multi-linestring
![Map Multi Linestring, awesome resource field for Nova](banner.jpg)

---

[![Version](http://poser.pugx.org/wm/map-multi-linestring/version)](https://packagist.org/packages/wm/map-multi-linestring)

- [map-multi-linestring](#map-multi-linestring)
  - [Requirements](#requirements)
  - [Installation](#installation)
  - [Develop](#develop)
  - [Usage](#usage)
    - [Map Multi Linestring](#map-multi-linestring-1)
  - [Configuration](#configuration)

## Requirements

- `php: ^8`
- `laravel/nova: ^4`

## Installation

You can install the package in to a Laravel app that uses [Nova](https://nova.laravel.com) via composer:

```bash
composer require wm/map-multi-linestring
```
## Develop
create a```nova-components``` folder in the root of the project where you want to develop.
Clone map-multi-linestring inside.
add  in ``` "repositories"``` array  attribute of ```composer.json```  
```php 
        {
            "type": "path",
            "url": "./nova-components/map-multi-linestring"
        }
```

modify  in ``` "requires"``` object  attribute of ```composer.json```  
```php 
    "wm/map-multi-linestring": "*",
```
in the first time

launch inside the repository hosting the field
```bash
    cd vendor/laravel/nova && npm install
```
we need modify composer.lock 
launch
```bash
    composer update wm/map-multi-linestring
```

launch inside field
```bash
    npm install
```

## Usage

### Map Multi Linestring

![image](field.png)

You can display a post gist geography(MultiLineString,4326) area on the map and change it by uploading a new MultiLineString file (.GPX, .KML, .GEOJSON).
To use the Map Multi Linestring feature, include the MapMultiLinestring class and add it to your resource's fields. Customize the map settings by providing metadata such as the initial map center, tile server URL, attribution text, minimum and maximum zoom levels, default zoom level, GraphHopper API URL used to specify the URL of the GraphHopper API, and GraphHopper routing profile used by GraphHopper when calculating the route. Routing profiles determine the type of transportation mode and the set of rules that the routing engine will use. The default value for this field is 'foot', which means the routing will be optimized for walking. Other available profiles include 'bike' and 'hike'.

```php
    use Wm\MapMultiLinestring\MapMultiLinestring;
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
            MapMultiLinestring::make('geometry')->withMeta([
                'center' => [42, 10],
                'attribution' => '<a href="https://webmapp.it/">Webmapp</a> contributors',
                'tiles' => 'https://api.webmapp.it/tiles/{z}/{x}/{y}.png',
                'minZoom' => 5,
                'maxZoom' => 17,
                'defaultZoom' => 10,
                'graphhopper_api' => 'https://graphhopper.webmapp.it/route',
                'graphhopper_profile' => 'hike'
            ]),
        ];
    }
```
## Configuration

As of v1.4.0 it's possible to use a `Tab` class instead of an array to represent your tabs.

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
        <td>'&lt;a href="https://www.openstreetmap.org/"&gt;OpenStreetMap&lt;/a&gt; contributors, &lt;a href="https://creativecommons.org/licenses/by-sa/2.0/"&gt;CC-BY-SA&lt;/a&gt;, Imagery (c) &lt;a href="https://www.mapbox.com/"&gt;Mapbox&lt;/a&gt;'</td>
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
        <td>1</td>
        <td>The minimum zoom level allowed on the map.</td>
      </tr>
      <tr>
        <td>maxZoom</td>
        <td>integer</td>
        <td>19</td>
        <td>The maximum zoom level allowed on the map.</td>
      </tr>
      <tr>
        <td>defaultZoom</td>
        <td>integer</td>
        <td>10</td>
        <td>The initial zoom level when the map is first displayed.</td>
      </tr>
      <tr>
        <td>graphhoopper_api</td>
        <td>string</td>
        <td>undefined</td>
        <td>The URL of the GraphHopper API used for routing requests.</td>
      </tr>
      <tr>
        <td>graphhopper_profile</td>
        <td>string</td>
        <td>'foot'</td>
        <td>The routing profile used by GraphHopper for calculating the route. Default is optimized for walking. All available profiles: 'bike', 'foot', and 'hike'.</td>
      </tr>
    </tbody>
  </table>
</div>




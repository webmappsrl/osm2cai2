@props(['hikingroute'])
@php
    use App\Models\hikingroute;

    $res = DB::select('SELECT ST_ASGeoJSON(geometry) as geojson from hiking_routes where id='.$hikingroute->id.'');

    $geometry = $res[0]->geojson;
    $geometry = json_decode($geometry);
    $geometry = $geometry->coordinates;
    $geometry = array_map(function($array){
            $new_array = [$array[1],$array[0]];
            return $new_array;
        },$geometry[0]);
    $geometry = json_encode($geometry);
@endphp
<x-schemaOrg :hikingroute="$hikingroute"/>
<div id="map" class="LeafletMap">
</div>
<script>
    var map = L.map('map', { dragging: !L.Browser.mobile }).setView([43.689740, 10.392279], 12);
    L.tileLayer('https://api.webmapp.it/tiles/{z}/{x}/{y}.png', {
        attribution: '<a  href="http://webmapp.it" target="blank"> © Webmapp </a><a _ngcontent-wbl-c140="" href="https://www.openstreetmap.org/about/" target="blank">© OpenStreetMap </a>',
        maxZoom: 16,
        minZoom: 8,
        tileSize: 256,
        scrollWheelZoom: false,
    }).addTo(map);
    var polyline = L.polyline({{$geometry}}, {color: 'white',weight:7}).addTo(map);
    var polyline2 = L.polyline({{$geometry}}, {color: 'red',weight:3}).addTo(map);
    
    // zoom the map to the polyline
    map.fitBounds(polyline.getBounds());
</script>
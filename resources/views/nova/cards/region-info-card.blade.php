<div class="region-info-card">
    <h3>{{ $regionName }}</h3>
    <p>&nbsp;</p>
    <a href="{{ $geojsonUrl }}" target="_blank">Download geojson Percorsi</a>
    <a href="{{ $shapefileUrl }}" target="_blank">Download shape Settori</a>
    <a href="{{ $csvUrl }}" target="_blank">Download CSV Percorsi</a>
    <p>&nbsp;</p>
    <p>Ultima sincronizzazione da osm: {{ $lastSync }}</p>
</div>

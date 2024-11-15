<!DOCTYPE html>
<html>

<head>
    <title>Mountain Group Hiking Routes Map</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            background-color: #f0f0f0;
        }

        h1 {
            color: #333;
            margin: 20px 0;
            text-align: center;
        }

        #map-container {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            max-width: 1200px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            background-color: #fff;
            border-radius: 10px;
            margin-bottom: 40px;
        }

        #map {
            height: 600px;
            width: 100%;
            border-radius: 10px;
        }

        .description {
            max-width: 1200px;
            text-align: center;
            color: #666;
            margin-bottom: 40px;
            padding: 0 20px;
        }
    </style>
</head>

<body>
    <h1>{{ $mountainGroup->name }}</h1>
    @if ($mountainGroup->description)
        <div class="description">
            <p>{{ $mountainGroup->description }}</p>
        </div>
    @endif
    <div id="map-container">
        <div id="map"></div>
    </div>

    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var map = L.map('map');

            L.tileLayer('https://api.webmapp.it/tiles/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            var geojsonFeature = {!! $geometry !!};

            var mountainGroupLayer = L.geoJSON(geojsonFeature).addTo(map);
            map.fitBounds(mountainGroupLayer.getBounds());

            var hikingRoutes = {!! $hikingRoutesGeojson !!};

            hikingRoutes.forEach(function(route) {
                L.geoJSON(route, {
                    style: function(feature) {
                        return {
                            color: 'red',
                            weight: 3,
                            opacity: 0.7
                        };
                    }
                }).addTo(map);
            });

            var allLayers = L.featureGroup([mountainGroupLayer]);
            hikingRoutes.forEach(function(route) {
                var layer = L.geoJSON(route);
                allLayers.addLayer(layer);
            });

            map.fitBounds(allLayers.getBounds());
        });
    </script>
</body>

</html>

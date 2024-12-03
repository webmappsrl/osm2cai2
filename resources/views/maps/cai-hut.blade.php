<!DOCTYPE html>
<html>

<head>
    <title>CAI Hut Map</title>
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
    <h1>{{ $caiHut->name }}</h1>
    @if ($caiHut->description)
        <div class="description">
            <p>{{ $caiHut->description }}</p>
        </div>
    @endif
    <div id="map-container">
        <div id="map"></div>
    </div>

    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var map = L.map('map').setView([{{ $latitude }}, {{ $longitude }}], 13);

            L.tileLayer('https://api.webmapp.it/tiles/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            L.marker([{{ $latitude }}, {{ $longitude }}]).addTo(map)
                .bindPopup('<b>{{ $caiHut->name }}</b><br>{{ $caiHut->description }}')
                .openPopup();
        });
    </script>
</body>

</html>

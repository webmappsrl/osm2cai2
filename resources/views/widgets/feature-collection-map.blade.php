<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <base href="/" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Feature Collection Widget Map</title>
    <link rel="stylesheet" href="https://cdn.statically.io/gh/webmappsrl/feature-collection-widget-map/master/dist/styles.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }
        .container {
            width: 100%;
            height: 100vh;
        }
        feature-collection-widget-map {
            width: 100%;
            height: 100%;
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <feature-collection-widget-map
            geojsonurl="{{ $geojsonUrl }}"
            strokeColor="rgba(0, 0, 255, 1)"
            strokeWidth="3"
            pointStrokeColor="rgba(0, 0, 255, 1)"
            pointStrokeWidth="3"
            fillColor="rgba(0, 0, 255, 0.3)"
            showControlZoom="true"
            mouseWheelZoom="true"
            dragPan="true"
            padding="50">
        </feature-collection-widget-map>
    </div>

    <!-- Script Angular -->
    <script src="https://cdn.statically.io/gh/webmappsrl/feature-collection-widget-map/refs/heads/master/dist_07_08_2025/runtime.js"></script>
    <script src="https://cdn.statically.io/gh/webmappsrl/feature-collection-widget-map/refs/heads/master/dist_07_08_2025/polyfills.js"></script>
    <script src="https://cdn.statically.io/gh/webmappsrl/feature-collection-widget-map/refs/heads/master/dist_07_08_2025/main.js"></script>
</body>
</html> 
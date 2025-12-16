<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trail Survey #{{ $trailSurvey->id }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }

        .header {
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .info-row {
            margin-bottom: 8px;
        }

        .label {
            font-weight: bold;
            display: inline-block;
            width: 150px;
        }

        .url-link {
            color: #0066cc;
            text-decoration: none;
            display: inline-block;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        .map-image {
            width: 100%;
            max-width: 100%;
            height: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 10px;
        }

        .map-container {
            page-break-inside: avoid;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Trail Survey #{{ $trailSurvey->id }}</h1>
    </div>

    <div class="section">
        <div class="section-title">Informazioni Generali</div>
        <div class="info-row">
            <span class="label">Hiking Route:</span>
            <span>{{ $trailSurvey->hikingRoute->name ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="label">URL:</span>
            @if ($trailSurvey->hikingRoute)
            @php
            $routeUrl = url("/resources/hiking-routes/{$trailSurvey->hikingRoute->id}");
            @endphp
            <a href="{{ $routeUrl }}" class="url-link">
                {{ $routeUrl }}
            </a>
            @else
            <span>N/A</span>
            @endif
        </div>
        <div class="info-row">
            <span class="label">Proprietario:</span>
            <span>{{ $trailSurvey->owner->name ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="label">Data Inizio:</span>
            <span>{{ $trailSurvey->start_date->format('d/m/Y') }}</span>
        </div>
        <div class="info-row">
            <span class="label">Data Fine:</span>
            <span>{{ $trailSurvey->end_date->format('d/m/Y') }}</span>
        </div>
        @if($trailSurvey->description)
        <div class="info-row" style="margin-top: 15px;">
            <div style="margin-bottom: 5px;">
                <span class="label">Descrizione:</span>
            </div>
            <div style="padding: 10px; background-color: #f9f9f9; border-left: 3px solid #2c3e50; white-space: pre-wrap; word-wrap: break-word;">
                {{ $trailSurvey->description }}
            </div>
        </div>
        @endif
    </div>

    @php
    // Ottieni il percorso dello screenshot della mappa
    $screenshotPath = $trailSurvey->getMapScreenshotPath();
    $storage = \Illuminate\Support\Facades\Storage::disk('public');
    $screenshotExists = $storage->exists($screenshotPath);

    // Converti l'immagine in base64 per DomPDF (metodo più affidabile)
    $screenshotBase64 = null;
    if ($screenshotExists) {
    try {
    $imageContent = $storage->get($screenshotPath);
    if ($imageContent && strlen($imageContent) > 0) {
    $screenshotBase64 = 'data:image/png;base64,' . base64_encode($imageContent);
    \Illuminate\Support\Facades\Log::info("Screenshot caricato per TrailSurvey {$trailSurvey->id}", [
    'path' => $screenshotPath,
    'size' => strlen($imageContent),
    'base64_length' => strlen($screenshotBase64),
    ]);
    } else {
    \Illuminate\Support\Facades\Log::warning("Screenshot vuoto per TrailSurvey {$trailSurvey->id}", [
    'path' => $screenshotPath,
    ]);
    }
    } catch (\Exception $e) {
    \Illuminate\Support\Facades\Log::error("Errore nel caricamento screenshot per TrailSurvey {$trailSurvey->id}: " . $e->getMessage(), [
    'path' => $screenshotPath,
    'trace' => $e->getTraceAsString(),
    ]);
    }
    } else {
    \Illuminate\Support\Facades\Log::warning("Screenshot non trovato per TrailSurvey {$trailSurvey->id}", [
    'path' => $screenshotPath,
    ]);
    }
    @endphp

    @if($screenshotExists && $screenshotBase64)
    <div class="section map-container">
        <div class="section-title">Mappa UGC Features</div>
        <img src="{{ $screenshotBase64 }}" alt="Mappa UGC Features" class="map-image" style="max-width: 100%; height: auto;" />
    </div>
    @else
    @if($screenshotExists)
    <div class="section map-container">
        <div class="section-title">Mappa UGC Features</div>
        <div style="padding: 20px; background-color: #f5f5f5; border: 1px solid #ddd; text-align: center; color: #666;">
            Immagine mappa non disponibile
        </div>
    </div>
    @endif
    @endif

    @if($trailSurvey->ugcPois->isNotEmpty())
    <div class="section">
        <div class="section-title">UGC POIs ({{ $trailSurvey->ugcPois->count() }})</div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                </tr>
            </thead>
            <tbody>
                @foreach($trailSurvey->ugcPois as $poi)
                <tr>
                    <td>{{ $poi->id }}</td>
                    <td>{{ $poi->name ?? 'N/A' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if($trailSurvey->ugcTracks->isNotEmpty())
    <div class="section">
        <div class="section-title">UGC Tracks ({{ $trailSurvey->ugcTracks->count() }})</div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                </tr>
            </thead>
            <tbody>
                @foreach($trailSurvey->ugcTracks as $track)
                <tr>
                    <td>{{ $track->id }}</td>
                    <td>{{ $track->name ?? 'N/A' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="section">
        <div style="font-size: 12px; color: #666; margin-top: 40px;">
            Generato il: {{ now()->format('d/m/Y H:i:s') }}
        </div>
    </div>
</body>

</html>
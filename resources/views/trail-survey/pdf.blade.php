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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
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
        <div class="info-row">
            <span class="label">Descrizione:</span>
            <span>{{ $trailSurvey->description }}</span>
        </div>
        @endif
    </div>

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


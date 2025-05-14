<?php

namespace App\Http\Controllers;

use App\Models\UgcPoi;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SourceSurveyController extends Controller
{
    public function overlayGeojson()
    {
        $sourceSurveys = $this->getValidatedSurveys();

        $output = [
            'type' => 'FeatureCollection',
            'features' => [],
        ];

        foreach ($sourceSurveys as $sourceSurvey) {
            $mediasHtml = $this->getMediasHtml($sourceSurvey);
            $osm2caiUrl = url('resources/source-surveys/'.$sourceSurvey->id);

            [$surveyName, $date, $flowRate, $temperature, $conductivity, $isActive] = $this->formatSurveyData($sourceSurvey);
            $htmlString = $this->getHtmlString($sourceSurvey, $mediasHtml, $surveyName, $date, $flowRate, $temperature, $conductivity, $isActive, $osm2caiUrl);

            $output['features'][] = [
                'type' => 'Feature',
                'properties' => [
                    'id' => $sourceSurvey->id,
                    'popup' => [
                        'html' => $htmlString,
                    ],
                ],
                'geometry' => json_decode(DB::select("select st_asGeojson(geometry) as geom from ugc_pois where id=$sourceSurvey->id;")[0]->geom, true),
            ];
        }

        return $output;
    }

    public function surveyGeoJson()
    {
        $sourceSurveys = $this->getValidatedSurveys();
        $features = $this->createFeatures($sourceSurveys);

        $geoJson = [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];

        return response()->json($geoJson);
    }

    public function surveyGpx()
    {
        $sourceSurveys = $this->getValidatedSurveys();
        $gpx = $this->createGpx($sourceSurveys);

        return response($gpx)
            ->header('Content-Type', 'application/gpx+xml')
            ->header('Content-Disposition', 'attachment; filename="survey.gpx"');
    }

    public function surveyKml()
    {
        $sourceSurveys = $this->getValidatedSurveys();
        $kml = $this->createKml($sourceSurveys);

        return response($kml)
            ->header('Content-Type', 'application/vnd.google-earth.kml+xml')
            ->header('Content-Disposition', 'attachment; filename="survey.kml"');
    }

    public function surveyShapefile()
    {
        $sourceSurveys = $this->getValidatedSurveys();
        $shapefileData = $this->createShapefile($sourceSurveys);

        if (Storage::disk('public')->exists($shapefileData)) {
            return response()->download(Storage::disk('public')->path($shapefileData), 'source_surveys.zip')->deleteFileAfterSend(true);
        } else {
            return response()->json(['error' => 'Shapefile creation failed'], 500);
        }
    }

    private function getValidatedSurveys()
    {
        return UgcPoi::where('form_id', 'water')
            ->where('validated', 'valid')
            ->get();
    }

    private function createFeatures($surveys)
    {
        $features = [];

        foreach ($surveys as $survey) {
            $rawData = $survey->raw_data;
            $position = $rawData['position'] ?? null;
            $altitude = $position['altitude'] ?? 'N/A';
            $feature = [
                'type' => 'Feature',
                'properties' => [
                    'id' => $survey->id,
                    'title' => $survey->name ?? 'N/A',
                    'active' => $rawData['active'] ?? 'N/A',
                    'info' => $survey->description ?? 'N/A',
                    'range_time' => $rawData['flow_rate'] ?? 'N/A',
                    'temperature' => $rawData['temperature'] ?? 'N/A',
                    'conductivity' => $rawData['conductivity'] ?? 'N/A',
                    'range_volume' => $rawData['range_volume'] ?? 'N/A',
                    'elevation' => $altitude,
                ],
                'geometry' => json_decode(DB::select("select st_asGeojson(geometry) as geom from ugc_pois where id=$survey->id;")[0]->geom, true),
            ];
            $features[] = $feature;
        }

        return $features;
    }

    private function createGpx($surveys)
    {
        $gpx = '<?xml version="1.0" encoding="UTF-8"?>';
        $gpx .= '<gpx version="1.1" creator="OSM2CAI" xmlns="http://www.topografix.com/GPX/1/1">';

        foreach ($surveys as $survey) {
            $rawData = $survey->raw_data;
            $geometry = json_decode(DB::select("select st_asGeojson(geometry) as geom from ugc_pois where id=$survey->id;")[0]->geom, true);
            $position = $rawData['position'] ?? null;
            $altitude = $position['altitude'] ?? 'N/A';

            $gpx .= '<wpt lat="'.$geometry['coordinates'][1].'" lon="'.$geometry['coordinates'][0].'">';
            $gpx .= '<name>'.htmlspecialchars($survey->name ?? 'N/A').'</name>';
            $gpx .= '<desc>'.htmlspecialchars($survey->description ?? 'N/A').'</desc>';
            $gpx .= '<ele>'.($altitude).'</ele>';
            $gpx .= '<extensions>';
            $gpx .= '<id>'.$survey->id.'</id>';
            $gpx .= '<active>'.($rawData['active'] ?? 'N/A').'</active>';
            $gpx .= '<range_time>'.($rawData['flow_rate'] ?? 'N/A').'</range_time>';
            $gpx .= '<temperature>'.($rawData['temperature'] ?? 'N/A').'</temperature>';
            $gpx .= '<conductivity>'.($rawData['conductivity'] ?? 'N/A').'</conductivity>';
            $gpx .= '<range_volume>'.($rawData['range_volume'] ?? 'N/A').'</range_volume>';
            $gpx .= '</extensions>';
            $gpx .= '</wpt>';
        }

        $gpx .= '</gpx>';

        return $gpx;
    }

    private function createKml($surveys)
    {
        $kml = '<?xml version="1.0" encoding="UTF-8"?>';
        $kml .= '<kml xmlns="http://www.opengis.net/kml/2.2">';
        $kml .= '<Document>';

        foreach ($surveys as $survey) {
            $rawData = $survey->raw_data;
            $geometry = json_decode(DB::select("select st_asGeojson(geometry) as geom from ugc_pois where id=$survey->id;")[0]->geom, true);
            $position = $rawData['position'] ?? null;
            $altitude = $position['altitude'] ?? 'N/A';

            $kml .= '<Placemark>';
            $kml .= '<name>'.htmlspecialchars($survey->name ?? 'N/A').'</name>';
            $kml .= '<description>';
            $kml .= 'ID: '.$survey->id."\n";
            $kml .= 'Info: '.htmlspecialchars($survey->description ?? 'N/A')."\n";
            $kml .= 'Active: '.($rawData['active'] ?? 'N/A')."\n";
            $kml .= 'Range Time: '.($rawData['flow_rate'] ?? 'N/A')."\n";
            $kml .= 'Temperature: '.($rawData['temperature'] ?? 'N/A')."\n";
            $kml .= 'Conductivity: '.($rawData['conductivity'] ?? 'N/A')."\n";
            $kml .= 'Range Volume: '.($rawData['range_volume'] ?? 'N/A')."\n";
            $kml .= 'Elevation: '.($altitude);
            $kml .= '</description>';
            $kml .= '<Point>';
            $kml .= '<coordinates>'.$geometry['coordinates'][0].','.$geometry['coordinates'][1].','.($rawData['altitude'] ?? 'N/A').'</coordinates>';
            $kml .= '</Point>';
            $kml .= '<ExtendedData>';
            $kml .= '<Data name="id"><value>'.$survey->id.'</value></Data>';
            $kml .= '<Data name="active"><value>'.($rawData['active'] ?? 'N/A').'</value></Data>';
            $kml .= '<Data name="range_time"><value>'.($rawData['flow_rate'] ?? 'N/A').'</value></Data>';
            $kml .= '<Data name="temperature"><value>'.($rawData['temperature'] ?? 'N/A').'</value></Data>';
            $kml .= '<Data name="conductivity"><value>'.($rawData['conductivity'] ?? 'N/A').'</value></Data>';
            $kml .= '<Data name="range_volume"><value>'.($rawData['range_volume'] ?? 'N/A').'</value></Data>';
            $kml .= '</ExtendedData>';
            $kml .= '</Placemark>';
        }

        $kml .= '</Document>';
        $kml .= '</kml>';

        return $kml;
    }

    private function createShapefile($surveys)
    {
        $surveys = $this->getValidatedSurveys();
        $name = 'source_surveys';

        Storage::disk('public')->makeDirectory('shape_files/zip');
        chdir(Storage::disk('public')->path('shape_files'));
        if (Storage::disk('public')->exists('shape_files/zip/'.$name.'.zip')) {
            Storage::disk('public')->delete('shape_files/zip/'.$name.'.zip');
        }

        $ids = $surveys->pluck('id')->toArray();

        $command = 'ogr2ogr -f "ESRI Shapefile" '.
            $name.
            '.shp PG:"dbname=\''.
            Config::get('database.connections.pgsql.database').
            '\' host=\''.
            Config::get('database.connections.pgsql.host').
            '\' port=\''.
            Config::get('database.connections.pgsql.port').
            '\' user=\''.
            Config::get('database.connections.pgsql.username').
            '\' password=\''.
            Config::get('database.connections.pgsql.password').
            '\'" -sql "SELECT id, name, description, raw_data->\'flow_rate\' as flow_rate, raw_data->\'temperature\' as temperature, raw_data->\'conductivity\' as conductivity, ST_Transform(geometry, 4326) as geometry FROM ugc_pois WHERE id IN ('.
            implode(',', $ids).
            ') AND form_id = \'water\' AND validated = \'valid\';" -a_srs EPSG:4326';

        exec($command);

        // Crea manualmente il file .prj
        $prjContent = 'GEOGCS["GCS_WGS_1984",DATUM["D_WGS_1984",SPHEROID["WGS_1984",6378137,298.257223563]],PRIMEM["Greenwich",0],UNIT["Degree",0.017453292519943295]]';
        file_put_contents($name.'.prj', $prjContent);

        $command = 'zip '.$name.'.zip '.$name.'.*';
        exec($command);

        $command = 'mv '.$name.'.zip zip/';
        exec($command);

        $command = 'rm '.$name.'.*';
        exec($command);

        $zipPath = 'shape_files/zip/'.$name.'.zip';

        return $zipPath;
    }

    /**
     * @OA\Get(
     *     path="/api/v2/source_survey/monitorings",
     *     summary="Get source survey monitorings",
     *     tags={"Api V2"},
     *     @OA\Response(
     *         response=200,
     *         description="source survey monitorings",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="validated",
     *                 type="integer",
     *                 format="integer"
     *             ),
     *             @OA\Property(
     *                 property="pending",
     *                 type="integer",
     *                 format="integer"
     *             ),
     *             @OA\Property(
     *                 property="people",
     *                 type="integer",
     *                 format="integer"
     *             )
     *         )
     *     )
     * )
     */
    public function surveyData()
    {
        $sourceSurveyPois = UgcPoi::where('form_id', 'water')->get();

        $validatedCount = $sourceSurveyPois->where('validated', 'valid')->count();
        $notValidatedCount = $sourceSurveyPois->where('validated', 'not_validated')->count();

        $peopleCount = User::whereHas('ugc_pois', function ($query) {
            $query->where('form_id', 'water');
        })->count();

        return response()->json([
            'validated' => $validatedCount,
            'pending' => $notValidatedCount,
            'people' => $peopleCount,
        ]);
    }

    private function getMediasHtml($sourceSurvey): string
    {
        $medias = $sourceSurvey->ugc_media()->get();
        if (count($medias) === 0) {
            $mediasHtml = <<<'HTML'
                        <div style="display: flex; justify-content: start;"> 'N/A'
                        HTML;
            $mediasHtml .= <<<'HTML'
                </div>
                HTML;
        } else {
            $mediasHtml = <<<'HTML'
                        <div style="display: flex; justify-content: start;">
                        HTML;
            foreach ($medias as $media) {
                $mediaUrl = $media->relative_url;
                // Check if the URL is already absolute
                if (! preg_match('~^(?:f|ht)tps?://~i', $mediaUrl)) {
                    $mediaUrl = url(Storage::url($mediaUrl));
                }
                $mediasHtml .= <<<HTML
                <a href="{$mediaUrl}" target="_blank">
                    <img src="{$mediaUrl}" style="width: 60px; margin-right: 5px; height: 60px; border: 1px solid #ccc; border-radius: 40%; padding: 2px;" alt="Thumbnail">
                </a>
                HTML;
            }
            $mediasHtml .= <<<'HTML'
                </div>
                HTML;
        }

        return $mediasHtml;
    }

    private function formatSurveyData($sourceSurvey): array
    {
        $rawData = $sourceSurvey->raw_data;
        $surveyName = $sourceSurvey->name ?? $rawData['title'] ?? 'N/A';

        $date = $rawData['date'] ?? $sourceSurvey->created_at ?? 'N/A';
        if ($date !== 'N/A') {
            $date = Carbon::parse($date)->format('d-m-Y');
        }
        $flowRate = $rawData['flow_rate'] ?? $sourceSurvey->calculateFlowRate() ?? 'N/A';
        if ($flowRate !== 'N/A') {
            $flowRate = str_replace(',', '.', $flowRate).' L/s';
        }
        $temperature = $rawData['temperature'] ?? 'N/A';
        if ($temperature !== 'N/A') {
            $temperature = str_replace(',', '.', $temperature).'C';
        }
        $conductivity = $rawData['conductivity'] ?? 'N/A';
        if ($conductivity !== 'N/A') {
            $conductivity = str_replace(',', '.', $conductivity).' microS/cm';
        }

        if (isset($rawData['active'])) {
            switch ($rawData['active']) {
                case 'yes':
                    $isActive = 'SI';
                    break;
                case 'no':
                    $isActive = 'NO';
                    break;
                default:
                    $isActive = 'N/A';
                    break;
            }
        } else {
            $isActive = 'N/A';
        }

        return [$surveyName, $date, $flowRate, $temperature, $conductivity, $isActive];
    }

    private function getHtmlString($sourceSurvey, $mediasHtml, $surveyName, $date, $flowRate, $temperature, $conductivity, $isActive, $osm2caiUrl): string
    {
        return <<<HTML
<div style='font-size: 1.1em; line-height: 1.4em;'>
    <strong>ID:</strong> <span style='white-space: pre-wrap;'>$sourceSurvey->id</span><br>
    <strong>Nome Sorgente:</strong> <span style='white-space: pre-wrap;'>$surveyName</span><br>
    <strong>Data del monitoraggio:</strong> <span style='white-space: pre-wrap;'>$date</span><br>
    <strong>Sorgente Attiva:</strong> <span style='white-space: pre-wrap;'>$isActive</span><br> 
    <strong>Portata:</strong> <span style='white-space: pre-wrap;'>$flowRate</span><br>
    <strong>Temperatura:</strong> <span style='white-space: pre-wrap;'>$temperature</span><br>
    <strong>Conducibilitá elettrica:</strong> <span style='white-space: pre-wrap;'>$conductivity</span><br>
    $mediasHtml <br>
    <a href="$osm2caiUrl" target="_blank" style="text-decoration: underline;">Vedi su OSM2CAI</a>
</div>
HTML;
    }
}

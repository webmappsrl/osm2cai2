<?php

namespace App\Nova\Actions;

use App\Exports\SignageProjectExport;
use App\Models\SignageProject;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Maatwebsite\Excel\Facades\Excel;
use Wm\WmPackage\Enums\ExportFormat;
use Wm\WmPackage\Nova\Actions\ExportTo;

/**
 * Action per esportare la segnaletica di tutte le HikingRoute di un SignageProject
 */
class ExportSignageProjectSignage extends ExportTo
{
    use InteractsWithQueue, Queueable;

    public function __construct()
    {
        parent::__construct(
            [],
            [],
            'signage-project-segnaletica',
            \Wm\WmPackage\Exporters\ModelExporter::DEFAULT_STYLE,
            ExportFormat::XLSX->value,
            ['properties']
        );
    }

    public function name()
    {
        return __('Esporta Segnaletica');
    }

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $format = isset($fields->format) ? $fields->format : $this->defaultFormat;
        
        // Prendi il primo progetto per il nome del file
        $firstProject = $models->first();
        $projectName = '';
        
        if ($firstProject instanceof SignageProject) {
            $projectName = $firstProject->getStringName();
            // Sanitizza il nome del progetto per il file system
            $projectName = $this->sanitizeFileName($projectName);
        }
        
        // Se il nome è vuoto, usa un fallback
        if (empty($projectName)) {
            $projectName = 'Progetto_' . ($firstProject->id ?? '');
        }
        
        // Genera il nome del file: OSM2CAI_Progetto_[nome_progetto]_Export_[data_ora].xls
        $dateTime = now()->format('Y-m-d_H-i');
        $fileName = 'OSM2CAI_Progetto_' . $projectName . '_Export_' . $dateTime . '.xls';

        // Prendi tutte le HikingRoute dai SignageProject selezionati
        // Carica le relazioni regions e provinces (con region della provincia) per evitare query N+1
        $hikingRoutes = collect();
        $hikingRouteIds = [];
        
        foreach ($models as $signageProject) {
            $projectRoutes = $signageProject->hikingRoutes()->with([
                'regions',
                'provinces.region' // Carica anche la relazione region della provincia
            ])->get();
            foreach ($projectRoutes as $route) {
                // Evita duplicati controllando l'ID
                if (!in_array($route->id, $hikingRouteIds)) {
                    $hikingRoutes->push($route);
                    $hikingRouteIds[] = $route->id;
                }
            }
        }

        // Usa il nuovo exporter con multiple sheets
        $exporter = new SignageProjectExport(
            $hikingRoutes,
            $this->columns,
            $this->styles
        );

        // Usa XLSX come formato (anche se l'estensione è .xls, Excel può gestirlo)
        // Oppure possiamo usare XLS se disponibile
        $exportFormat = ExportFormat::XLSX->value;

        Excel::store(
            $exporter,
            $fileName,
            'public',
            $exportFormat,
        );

        $signedUrl = URL::temporarySignedRoute(
            'download.export',
            now()->addMinutes(5),
            ['fileName' => $fileName]
        );

        return Action::redirect($signedUrl);
    }

    /**
     * Sanitizza il nome del file rimuovendo caratteri non validi
     *
     * @param string $fileName
     * @return string
     */
    protected function sanitizeFileName(string $fileName): string
    {
        // Rimuovi caratteri non validi per nomi file
        $fileName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $fileName);
        
        // Rimuovi spazi multipli e sostituiscili con underscore
        $fileName = preg_replace('/\s+/', '_', $fileName);
        
        // Rimuovi underscore multipli
        $fileName = preg_replace('/_+/', '_', $fileName);
        
        // Rimuovi underscore all'inizio e alla fine
        $fileName = trim($fileName, '_');
        
        // Limita la lunghezza (max 100 caratteri per sicurezza)
        if (strlen($fileName) > 100) {
            $fileName = substr($fileName, 0, 100);
        }
        
        // Se è vuoto dopo la sanitizzazione, usa un fallback
        if (empty($fileName)) {
            $fileName = 'Progetto';
        }
        
        return $fileName;
    }
}

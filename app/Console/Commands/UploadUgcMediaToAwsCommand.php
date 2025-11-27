<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;
use Wm\WmPackage\Models\Media;

class UploadUgcMediaToAwsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:upload-ugc-media-to-aws {--dry-run : Esegue solo una simulazione senza modificare nulla} {--ugc-poi-id= : Processa solo i media di un UgcPoi specifico (ID del model)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Carica i file media da public/storage/ugc-media (cercati con file_name) su AWS (con nome name) e sincronizza file_name con name';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('⚠️  Modalità DRY RUN - Nessuna modifica verrà applicata');
            $this->newLine();
        }

        $ugcPoiId = $this->option('ugc-poi-id');

        if ($ugcPoiId) {
            $this->info("Processando solo i media del UgcPoi ID: {$ugcPoiId}");
        } else {
            $this->info('Inizio caricamento media su AWS (solo UgcPoi e UgcTrack)...');
        }

        $query = Media::whereColumn('name', '!=', 'file_name')
            ->whereIn('model_type', [
                'App\Models\UgcPoi',
                'App\Models\UgcTrack',
                'Wm\WmPackage\Models\UgcPoi',
                'Wm\WmPackage\Models\UgcTrack',
            ]);

        // Filtra per UgcPoi specifico se richiesto
        if ($ugcPoiId) {
            $query->where('model_type', 'App\Models\UgcPoi')
                ->where('model_id', $ugcPoiId);
        }

        $mediaWithDifferentNames = $query->get();

        if ($mediaWithDifferentNames->isEmpty()) {
            $this->info('Nessun media trovato con name e file_name diversi per UgcPoi o UgcTrack.');

            return 0;
        }

        $this->info("Trovati {$mediaWithDifferentNames->count()} media da processare.");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($mediaWithDifferentNames->count());
        $progressBar->start();

        $uploaded = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        $localPath = public_path('storage/ugc-media');
        $awsDisk = Storage::disk('wmfe');

        if ($ugcPoiId && $mediaWithDifferentNames->isNotEmpty()) {
            $this->newLine();
            $this->info("Media trovati per UgcPoi ID {$ugcPoiId}:");
            foreach ($mediaWithDifferentNames as $m) {
                $this->line("  - Media ID: {$m->id}, Name: {$m->name}, File Name: {$m->file_name}");
            }
            $this->newLine();
        }

        foreach ($mediaWithDifferentNames as $media) {
            try {
                // Cerca il file locale usando file_name
                $localFilePath = $localPath.'/'.$media->file_name;

                // Verifica se il file esiste localmente
                if (! file_exists($localFilePath)) {
                    $skipped++;
                    $errors[] = "Media ID {$media->id}: File non trovato localmente - {$media->file_name}";
                    $progressBar->advance();
                    continue;
                }

                // Usa name come nome file su AWS
                $pathGenerator = PathGeneratorFactory::create($media);
                $awsFilePath = $pathGenerator->getPath($media).'/'.$media->name;

                if (! $dryRun && $awsDisk->exists($awsFilePath)) {
                    // Il file esiste già su AWS, aggiorna solo file_name
                    $media->file_name = $media->name;
                    $media->saveQuietly();
                    $uploaded++;
                    $progressBar->advance();
                    continue;
                }

                // Leggi il file locale
                $fileContent = file_get_contents($localFilePath);
                $fileSize = filesize($localFilePath);

                if ($fileContent === false) {
                    $failed++;
                    $errors[] = "Media ID {$media->id}: Impossibile leggere il file locale - {$media->file_name}";
                    $progressBar->advance();
                    continue;
                }

                if (! $dryRun) {
                    // Carica il file su AWS usando name come nome file
                    $uploadedSuccessfully = $awsDisk->put($awsFilePath, $fileContent);

                    if (! $uploadedSuccessfully) {
                        $failed++;
                        $errors[] = "Media ID {$media->id}: Errore durante il caricamento su AWS - {$media->name}";
                        $progressBar->advance();
                        continue;
                    }

                    // Aggiorna il record Media: sincronizza file_name con name
                    $media->file_name = $media->name;
                    $media->size = $fileSize;
                    $media->disk = 'wmfe';
                    $media->saveQuietly();
                }

                $uploaded++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "Media ID {$media->id}: Errore - {$e->getMessage()}";
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Mostra i risultati
        $this->info('✅ Completato!');
        $this->table(
            ['Risultato', 'Conteggio'],
            [
                ['Caricati/Aggiornati', $uploaded],
                ['Saltati (file non trovati)', $skipped],
                ['Falliti', $failed],
            ]
        );

        // Mostra sempre gli errori se si testa un singolo UgcPoi o se verbose è attivo
        if (! empty($errors) && ($ugcPoiId || $this->option('verbose'))) {
            $this->newLine();
            $this->warn('Errori riscontrati:');
            foreach (array_slice($errors, 0, 20) as $error) {
                $this->line("  - {$error}");
            }
            if (count($errors) > 20) {
                $this->line('  ... e altri '.(count($errors) - 20).' errori');
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('⚠️  Questa era una simulazione. Esegui senza --dry-run per applicare le modifiche.');
        }

        return 0;
    }
}

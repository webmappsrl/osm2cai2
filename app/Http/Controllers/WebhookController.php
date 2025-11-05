<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Wm\WmPackage\Models\UgcPoi;
use Wm\WmPackage\Models\UgcTrack;
use Wm\WmPackage\Services\GeometryComputationService;
use Wm\WmPackage\Http\Controllers\Controller;

class WebhookController extends Controller
{
    /**
     * Handle webhook for UGC POI creation/update from geohub
     */
    public function ugcPoi(Request $request): JsonResponse
    {
        return $this->processUgcWebhook(
            $request,
            \Wm\WmPackage\Http\Controllers\Api\UgcPoiController::class,
            \App\Models\UgcPoi::class
        );
    }

    /**
     * Handle webhook for UGC Track creation/update from geohub
     */
    public function ugcTrack(Request $request): JsonResponse
    {
        return $this->processUgcWebhook(
            $request,
            \Wm\WmPackage\Http\Controllers\Api\UgcTrackController::class,
            \App\Models\UgcTrack::class
        );
    }

    /**
     * Processa un webhook UGC (POI o Track) con logica unificata
     * 
     * @param \Illuminate\Http\Request $request
     * @param string $controllerClass Nome completo della classe controller
     * @param string $modelClass Nome completo della classe model
     * @return JsonResponse
     */
    private function processUgcWebhook(Request $request, string $controllerClass, string $modelClass): JsonResponse
    {
        // Deriva il tipo di modello dal nome della classe (es: UgcPoi -> POI, UgcTrack -> Track)
        $modelType = str_replace('Ugc', '', class_basename($modelClass));

        Log::info("Webhook: UGC {$modelType} request received", [
            'method' => $request->method(),
            'headers' => $request->headers->all(),
            'body_preview' => substr($request->getContent(), 0, 200),
            'has_files' => $request->hasFile('images'),
            'files_count' => $request->hasFile('images') ? count($request->allFiles()['images'] ?? []) : 0
        ]);

        // Preserva i dati originali prima di modificare la richiesta
        $originalData = $request->all();
        $action = $originalData['action'] ?? null;

        Log::info("Webhook: Original data received for {$modelType}", [
            'original_data_keys' => array_keys($originalData),
            'action' => $action,
            'original_data' => $originalData
        ]);

        // Step 1: Decodifica il feature (solo decodifica, nessuna modifica)
        $feature = $this->decodeFeature($originalData['feature'] ?? null, $modelType);
        if ($feature === null) {
            return response()->json(['error' => 'Invalid feature JSON'], 400);
        }


        // Gestisci le immagini usando la funzione unificata
        $imagesArray = $this->handleImages($request, null, $modelType);

        Log::info("Webhook: Files reorganized for {$modelType}", [
            'images_count' => count($imagesArray),
            'feature_decoded' => $feature
        ]);

        // Crea una nuova richiesta con i dati modificati
        $newRequestData = [
            'action' => $action,
            'feature' => json_encode($feature)
        ];

        // Crea una nuova richiesta Request
        $newRequest = Request::create(
            $request->url(),
            $request->method(),
            $newRequestData,
            $request->cookies->all(),
            [], // files - li gestiamo separatamente dopo
            $request->server->all(),
            $request->getContent()
        );

        // Copia gli headers dalla richiesta originale
        foreach ($request->headers->all() as $key => $values) {
            $newRequest->headers->set($key, $values);
        }

        // Copia il routing dalla richiesta originale
        if ($request->route()) {
            $newRequest->setRouteResolver(function () use ($request) {
                return $request->route();
            });
        }

        // Log per debug della richiesta finale
        Log::info("Webhook: Final request structure for {$modelType}", [
            'feature_type' => gettype($newRequest->input('feature')),
            'feature_value' => $newRequest->input('feature'),
            'has_files' => $newRequest->hasFile('feature'),
            'files_count' => count($newRequest->allFiles()),
            'content_type' => $newRequest->header('Content-Type')
        ]);

        Log::info("Webhook: Request prepared for {$modelType} controller", [
            'feature_type' => gettype($feature),
            'images_count' => count($imagesArray),
            'has_feature' => isset($feature),
            'has_images' => !empty($imagesArray)
        ]);

        try {
            $controller = app($controllerClass);

            // Log della richiesta finale per debug
            Log::info("Webhook: Final request for {$modelType} controller", [
                'action' => $action,
                'request_all' => $newRequest->all(),
                'request_has_files' => $newRequest->hasFile('images'),
                'request_files_count' => count($newRequest->allFiles())
            ]);

            // Gestione create/update per Track (POI ha solo create tramite store)
            $response = null;
            if ($modelType === 'Track' && $action === 'update') {
                $ugcId = $originalData['ugc_id'] ?? null;
                $ugcModel = $ugcId ? $modelClass::find($ugcId) : null;
                if (!$ugcModel) {
                    Log::error("Webhook: UGC {$modelType} not found", ['ugc_id' => $ugcId]);
                    return response()->json(['error' => "UGC {$modelType} not found"], 404);
                }
                $response = $controller->update($newRequest, $ugcModel);
            } else {
                // Store per POI e create per Track
                $response = $controller->store($newRequest);
            }

            // Post-processing dopo la creazione
            if ($response && $response->getStatusCode() === 201) {
                // Estrai l'ID dalla risposta JSON
                $responseContent = $response->getContent();
                $responseData = json_decode($responseContent, true);
                $ugcModelId = $responseData['id'] ?? null;

                if ($ugcModelId) {
                    $ugcModel = $modelClass::find($ugcModelId);
                    if ($ugcModel) {
                        // Gestisce l'associazione dell'utente e created_by usando la funzione unificata
                        $this->handleUserAssociation($request, $ugcModel, $modelType, $ugcModelId);

                        // Associa le immagini al modello usando la funzione unificata
                        $this->associateImagesToModel($ugcModel, $imagesArray, $modelType, $ugcModelId);
                    }
                }
            }

            return $response;
        } catch (\Exception $e) {
            Log::error("Webhook: UGC {$modelType} processing error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_all' => $request->all(),
                'request_content' => $request->getContent(),
            ]);
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Mappa gli app ID da geohub a osm2cai
     * 
     * @param int $originalAppId
     * @return int
     */
    private function mapAppId(int $originalAppId): int
    {
        $mapping = [
            26 => 1, // it.webmapp.osm2cai -> osm2cai
            20 => 2, // it.webmapp.sicai -> sicai
            58 => 3, // it.webmapp.acquasorgente -> acquasorgente
        ];

        $mappedAppId = $mapping[$originalAppId] ?? $originalAppId;

        Log::info('Webhook: App ID mapping applied', [
            'input_app_id' => $originalAppId,
            'output_app_id' => $mappedAppId,
            'mapping_found' => isset($mapping[$originalAppId]),
            'mapping_rule' => isset($mapping[$originalAppId]) ? "{$originalAppId} -> {$mappedAppId}" : 'no mapping (using original)'
        ]);

        return $mappedAppId;
    }

    /**
     * Gestisce la decodifica del feature che può arrivare come stringa JSON, array o file
     * 
     * @param mixed $feature Il feature da decodificare
     * @param string $modelType Tipo di modello per logging ('POI' o 'Track')
     * @return array|null Il feature decodificato o null in caso di errore
     */
    private function decodeFeature($feature, string $modelType): ?array
    {
        Log::info("Webhook: Feature received for {$modelType}", [
            'feature_type' => gettype($feature),
            'feature_class' => is_object($feature) ? get_class($feature) : 'N/A',
            'feature_raw' => $feature
        ]);

        // Se il feature è un file, leggi il contenuto
        if ($feature instanceof \Illuminate\Http\UploadedFile) {
            $featureContent = $feature->get();
            Log::info("Webhook: Feature file content for {$modelType}", [
                'content_length' => strlen($featureContent),
                'content_preview' => substr($featureContent, 0, 200)
            ]);

            $feature = json_decode($featureContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("Webhook: Error decoding feature file JSON for {$modelType}", [
                    'content' => $featureContent,
                    'json_error' => json_last_error_msg()
                ]);
                return null;
            }
        } elseif (is_string($feature)) {
            $feature = json_decode($feature, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("Webhook: Error decoding feature JSON for {$modelType}", [
                    'feature_string' => $feature,
                    'json_error' => json_last_error_msg()
                ]);
                return null;
            }
        }

        Log::info("Webhook: Feature decoded for {$modelType}", [
            'feature_decoded' => $feature,
            'properties' => $feature['properties'] ?? 'N/A',
            'geometry' => isset($feature['geometry']) ? 'Present' : 'Missing'
        ]);

        return $feature;
    }



    /**
     * Gestisce le immagini per un modello UGC (POI o Track)
     * 
     * @param \Illuminate\Http\Request $request
     * @param mixed $model Il modello UGC (UgcPoi o UgcTrack)
     * @param string $modelType Tipo di modello per logging ('POI' o 'Track')
     * @return array Array delle immagini processate
     */
    private function handleImages(Request $request, $model, string $modelType): array
    {
        // Preserva i file se presenti
        if ($request->hasFile('images')) {
            $allFiles = $request->allFiles();
            $imagesCount = 0;

            // Conta i file con pattern images[0], images[1], ecc.
            foreach ($allFiles as $key => $file) {
                if (preg_match('/^images\[\d+\]$/', $key)) {
                    $imagesCount++;
                }
            }

            Log::info("Webhook: Processing images for {$modelType}", [
                'images_count' => $imagesCount,
                'all_files_keys' => array_keys($allFiles)
            ]);
        }

        // Riorganizza la richiesta per il controller del package
        $allFiles = $request->allFiles();
        $imagesArray = [];

        // Gestisci sia il caso di images[0], images[1] che images come array
        if (isset($allFiles['images'])) {
            // Se images è già un array di file
            if (is_array($allFiles['images'])) {
                $imagesArray = $allFiles['images'];
            } else {
                // Se images è un singolo file
                $imagesArray = [$allFiles['images']];
            }
        } else {
            // Cerca file con pattern images[0], images[1], ecc.
            foreach ($allFiles as $key => $file) {
                if (preg_match('/^images\[\d+\]$/', $key)) {
                    $imagesArray[] = $file;
                }
            }
        }

        Log::info("Webhook: Files reorganized for {$modelType}", [
            'all_files_keys' => array_keys($allFiles),
            'images_count' => count($imagesArray)
        ]);

        return $imagesArray;
    }

    /**
     * Associa le immagini a un modello UGC dopo la creazione
     * 
     * @param mixed $model Il modello UGC (UgcPoi o UgcTrack)
     * @param array $imagesArray Array delle immagini da associare
     * @param string $modelType Tipo di modello per logging ('POI' o 'Track')
     * @param int $modelId ID del modello per logging
     */
    private function associateImagesToModel($model, array $imagesArray, string $modelType, int $modelId): void
    {
        if (!empty($imagesArray)) {
            foreach ($imagesArray as $image) {
                if ($image instanceof \Illuminate\Http\UploadedFile) {
                    $model->addMedia($image)
                        ->toMediaCollection('default');
                }
            }
            Log::info("Webhook: Associated images with {$modelType}", [
                strtolower($modelType) . '_id' => $modelId,
                'images_count' => count($imagesArray)
            ]);
        }
    }

    /**
     * Gestisce l'associazione dell'utente e imposta created_by per un modello UGC
     * 
     * @param \Illuminate\Http\Request $request
     * @param mixed $model Il modello UGC (UgcPoi o UgcTrack)
     * @param string $modelType Tipo di modello per logging ('POI' o 'Track')
     * @param int $modelId ID del modello per logging
     */
    private function handleUserAssociation(Request $request, $model, string $modelType, int $modelId): void
    {
        // Imposta created_by come 'device'
        $model->created_by = 'device';

        // - geohub_id: ID originale di geohub
        // - geohub_app_id: app_id originale di geohub (26)
        // - app_id: app_id mappato per osm2cai (1)
        // - form_id: ID del form se presente
        // - name: nome dell'elemento

        // DEBUG: Log dei valori prima dell'assegnazione
        Log::info("Webhook: Debug properties before assignment", [
            'properties' => $model->properties ?? 'null',
            'geohub_id' => $model->properties['geohub_id'] ?? 'not set',
            'geohub_app_id' => $model->properties['geohub_app_id'] ?? 'not set',
            'app_id' => $model->properties['app_id'] ?? 'not set'
        ]);

        // Controlla se i campi esistono nel database prima di assegnarli
        $assignedFields = [];
        $tableName = $model->getTable();
        $existingColumns = \DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = '{$tableName}'");
        $columnNames = array_column($existingColumns, 'column_name');
        $fieldsToUpdate = ['geohub_id', 'geohub_app_id', 'app_id', 'form_id', 'name', 'description'];

        foreach ($fieldsToUpdate as $field) {
            if (in_array($field, $columnNames) && isset($model->properties[$field])) {
                $model->{$field} = $model->properties[$field] ?? null;
                $assignedFields[$field] = $model->{$field};
            }
        }

        Log::info("Webhook: Model attributes assigned", [
            'model_type' => $modelType,
            'model_id' => $modelId,
            'table_name' => $tableName,
            'existing_columns' => $columnNames,
            'assigned_fields' => $assignedFields
        ]);

        Log::info("Webhook: Fields assigned for {$modelType}", [
            'model_id' => $modelId,
            'geohub_id' => $model->geohub_id,
            'geohub_app_id' => $model->geohub_app_id,
            'app_id' => $model->app_id,
            'form_id' => $model->form_id
        ]);

        // Associa l'utente basandomi sull'header X-Geohub-User-Email
        $userEmail = $request->header('X-Geohub-User-Email');
        if ($userEmail) {
            $user = $this->findOrCreateUser($userEmail);
            if ($user) {
                $model->user_id = $user->id;
                Log::info("Webhook: Associated {$modelType} with user", [
                    strtolower($modelType) . '_id' => $modelId,
                    'user_email' => $userEmail,
                    'user_id' => $user->id
                ]);
            }
        }

        $model->saveQuietly();

        Log::info("Webhook: Updated {$modelType} created_by to device", [
            strtolower($modelType) . '_id' => $modelId
        ]);
    }

    /**
     * Trova o crea un utente basandomi sull'email
     * 
     * @param string $email
     * @return User|null
     */
    private function findOrCreateUser(string $email): ?User
    {
        try {
            // Cerca l'utente esistente
            $user = User::where('email', $email)->first();

            if ($user) {
                Log::info('Webhook: Found existing user', [
                    'email' => $email,
                    'user_id' => $user->id
                ]);
                return $user;
            }

            // Crea un nuovo utente se non esiste
            $user = User::create([
                'name' => $email,
                'email' => $email,
                'password' => bcrypt('webmapp123'), // Password temporanea
            ]);

            // Assegna il ruolo Guest
            $user->assignRole(UserRole::Guest);

            Log::info('Webhook: Created new user', [
                'email' => $email,
                'user_id' => $user->id
            ]);

            return $user;
        } catch (\Exception $e) {
            Log::error('Webhook: Error finding/creating user', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}

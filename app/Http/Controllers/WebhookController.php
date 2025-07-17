<?php

namespace App\Http\Controllers;

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
        Log::info('Webhook: UGC POI request received', [
            'method' => $request->method(),
            'headers' => $request->headers->all(),
            'body_preview' => substr($request->getContent(), 0, 200),
            'has_files' => $request->hasFile('images'),
            'files_count' => $request->hasFile('images') ? count($request->allFiles()['images'] ?? []) : 0
        ]);

        // Preserva i dati originali prima di modificare la richiesta
        $originalData = $request->all();
        $action = $originalData['action'] ?? null;

        Log::info('Webhook: Original data received', [
            'original_data_keys' => array_keys($originalData),
            'action' => $action,
            'original_data' => $originalData
        ]);

        // Gestisci il feature che può arrivare come stringa JSON, array o file
        $feature = $originalData['feature'] ?? null;
        Log::info('Webhook: Feature received', [
            'feature_type' => gettype($feature),
            'feature_class' => is_object($feature) ? get_class($feature) : 'N/A',
            'feature_raw' => $feature
        ]);

        // Se il feature è un file, leggi il contenuto
        if ($feature instanceof \Illuminate\Http\UploadedFile) {
            $featureContent = $feature->get();
            Log::info('Webhook: Feature file content', [
                'content_length' => strlen($featureContent),
                'content_preview' => substr($featureContent, 0, 200)
            ]);

            $feature = json_decode($featureContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Webhook: Error decoding feature file JSON', [
                    'content' => $featureContent,
                    'json_error' => json_last_error_msg()
                ]);
                return response()->json(['error' => 'Invalid feature JSON in file'], 400);
            }
        } elseif (is_string($feature)) {
            $feature = json_decode($feature, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Webhook: Error decoding feature JSON', [
                    'feature_string' => $feature,
                    'json_error' => json_last_error_msg()
                ]);
                return response()->json(['error' => 'Invalid feature JSON'], 400);
            }
        }

        Log::info('Webhook: Feature decoded', [
            'feature_decoded' => $feature,
            'properties' => $feature['properties'] ?? 'N/A',
            'geometry' => isset($feature['geometry']) ? 'Present' : 'Missing'
        ]);


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

            Log::info('Webhook: Processing images for POI', [
                'images_count' => $imagesCount,
                'all_files_keys' => array_keys($allFiles)
            ]);
        }


        // Map app_id from geohub to osm2cai2 se presente nel feature
        if (isset($feature) && isset($feature['properties']['app_id'])) {
            $originalAppId = $feature['properties']['app_id'];
            $mappedAppId = $this->mapAppId($originalAppId);
            Log::info('Webhook: App ID mapping for POI', [
                'original_app_id' => $originalAppId,
                'mapped_app_id' => $mappedAppId,
                'action' => $action
            ]);
            $feature['properties']['app_id'] = $mappedAppId;
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

        Log::info('Webhook: Files reorganized', [
            'all_files_keys' => array_keys($allFiles),
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
        Log::info('Webhook: Final request structure', [
            'feature_type' => gettype($newRequest->input('feature')),
            'feature_value' => $newRequest->input('feature'),
            'has_files' => $newRequest->hasFile('feature'),
            'files_count' => count($newRequest->allFiles()),
            'content_type' => $newRequest->header('Content-Type')
        ]);

        Log::info('Webhook: Request prepared for controller', [
            'feature_type' => gettype($feature),
            'images_count' => count($imagesArray),
            'has_feature' => isset($feature),
            'has_images' => !empty($imagesArray)
        ]);
        try {
            $controller = app(\Wm\WmPackage\Http\Controllers\Api\UgcPoiController::class);

            // Log della richiesta finale per debug
            Log::info('Webhook: Final request for controller', [
                'action' => $action,
                'request_all' => $newRequest->all(),
                'request_has_files' => $newRequest->hasFile('images'),
                'request_files_count' => count($newRequest->allFiles())
            ]);

            // Chiama direttamente il controller store con la nuova richiesta
            $response = $controller->store($newRequest);

            // Dopo la creazione, aggiorna il modello per impostare created_by come 'device' e associare l'utente
            if ($response->getStatusCode() === 201) {
                // Estrai l'ID dalla risposta JSON
                $responseContent = $response->getContent();
                $responseData = json_decode($responseContent, true);
                $ugcPoiId = $responseData['id'] ?? null;

                if ($ugcPoiId) {
                    $ugcPoi = \App\Models\WmUgcPoi::find($ugcPoiId);
                    if ($ugcPoi) {
                        // Imposta created_by come 'device'
                        $ugcPoi->created_by = 'device';

                        // Associa l'utente basandomi sull'header X-Geohub-User-Email
                        $userEmail = $request->header('X-Geohub-User-Email');
                        if ($userEmail) {
                            $user = $this->findOrCreateUser($userEmail);
                            if ($user) {
                                $ugcPoi->user_id = $user->id;
                                Log::info('Webhook: Associated POI with user', [
                                    'poi_id' => $ugcPoiId,
                                    'user_email' => $userEmail,
                                    'user_id' => $user->id
                                ]);
                            }
                        }

                        $ugcPoi->saveQuietly();

                        // Associa le immagini al POI se presenti
                        if (!empty($imagesArray)) {
                            foreach ($imagesArray as $image) {
                                if ($image instanceof \Illuminate\Http\UploadedFile) {
                                    $ugcPoi->addMedia($image)
                                        ->toMediaCollection('default');
                                }
                            }
                            Log::info('Webhook: Associated images with POI', [
                                'poi_id' => $ugcPoiId,
                                'images_count' => count($imagesArray)
                            ]);
                        }

                        Log::info('Webhook: Updated POI created_by to device', [
                            'poi_id' => $ugcPoiId,
                            'action' => $action
                        ]);
                    }
                }
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('Webhook: UGC POI processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_all' => $request->all(),
                'request_content' => $request->getContent(),
            ]);
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Handle webhook for UGC Track creation/update from geohub
     */
    public function ugcTrack(Request $request): JsonResponse
    {
        Log::info('Webhook: UGC Track request received', [
            'method' => $request->method(),
            'headers' => $request->headers->all(),
            'body_preview' => substr($request->getContent(), 0, 200),
            'has_files' => $request->hasFile('images'),
            'files_count' => $request->hasFile('images') ? count($request->allFiles()['images'] ?? []) : 0
        ]);

        try {
            $data = $request->all();
            $action = $data['action'] ?? null;
            $feature = $data['feature'] ?? null;

            // Map app_id from geohub to osm2cai2 se presente nel feature
            if (isset($feature['properties']['app_id'])) {
                $originalAppId = $feature['properties']['app_id'];
                $mappedAppId = $this->mapAppId($originalAppId);
                Log::info('Webhook: App ID mapping for Track', [
                    'original_app_id' => $originalAppId,
                    'mapped_app_id' => $mappedAppId,
                    'action' => $action
                ]);
                $feature['properties']['app_id'] = $mappedAppId;
            }

            // Modifica direttamente la richiesta originale per preservare i file
            $request->merge(['feature' => $feature]);

            $controller = app(\Wm\WmPackage\Http\Controllers\Api\UgcTrackController::class);
            if ($action === 'create') {
                return $controller->store($request);
            } elseif ($action === 'update') {
                $ugcId = $data['ugc_id'] ?? null;
                $ugcTrack = $ugcId ? \Wm\WmPackage\Models\UgcTrack::find($ugcId) : null;
                if (!$ugcTrack) {
                    Log::error('Webhook: UGC Track not found', ['ugc_id' => $ugcId]);
                    return response()->json(['error' => 'UGC Track not found'], 404);
                }
                return $controller->update($request, $ugcTrack);
            } else {
                return response()->json(['error' => 'Invalid or missing action'], 400);
            }
        } catch (\Exception $e) {
            Log::error('Webhook: UGC Track processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
            $user->assignRole('Guest');

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

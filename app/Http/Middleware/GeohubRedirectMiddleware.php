<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

class GeohubRedirectMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        Log::info('GeohubRedirectMiddleware: request body', [
            'body' => $request->getContent()
        ]);

        // Controlla se la richiesta è un redirect da geohub
        if ($request->header('X-Geohub-Redirect') === 'true') {
            Log::info('Geohub redirect detected', [
                'url' => $request->url(),
                'method' => $request->method(),
                'headers' => [
                    'X-Geohub-Redirect' => $request->header('X-Geohub-Redirect'),
                    'X-Geohub-Source' => $request->header('X-Geohub-Source'),
                    'X-Geohub-Original-App-Id' => $request->header('X-Geohub-Original-App-Id'),
                    'app-id' => $request->header('app-id'),
                    'Authorization' => $request->header('Authorization') ? 'present' : 'missing',
                    'Authorization_value_full' => $request->header('Authorization'),
                ]
            ]);

            // Gestisci l'autenticazione JWT da geohub
            $this->handleGeohubAuthentication($request);

            // Rimappa l'app ID se presente nell'header
            $originalAppId = $request->header('X-Geohub-Original-App-Id');
            if ($originalAppId) {
                $mappedAppId = $this->mapAppId($originalAppId);
                
                Log::info('Processing app ID mapping', [
                    'original_app_id' => $originalAppId,
                    'mapped_app_id' => $mappedAppId,
                    'mapping_applied' => $originalAppId !== $mappedAppId
                ]);
                
                // Sostituisce l'app-id nell'header se presente
                $currentHeaderAppId = $request->header('app-id');
                if ($currentHeaderAppId) {
                    Log::info('Updating app-id header', [
                        'old_value' => $currentHeaderAppId,
                        'new_value' => $mappedAppId
                    ]);
                    $request->headers->set('app-id', $mappedAppId);
                }
                
                // Gestisci il body per richieste POST/PUT/PATCH
                if (in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
                    Log::info('Processing request body for method: ' . $request->method());
                    $this->processRequestBody($request, $mappedAppId);
                }
                
                Log::info('Geohub redirect processing completed', [
                    'original_app_id' => $originalAppId,
                    'mapped_app_id' => $mappedAppId,
                    'method' => $request->method(),
                    'has_body_processing' => in_array($request->method(), ['POST', 'PUT', 'PATCH'])
                ]);
            } else {
                Log::warning('Geohub redirect detected but no X-Geohub-Original-App-Id header found');
            }
        }

        $response = $next($request);
        Log::info('GeohubRedirectMiddleware: response body', [
            'body' => method_exists($response, 'getContent') ? $response->getContent() : 'not available'
        ]);
        return $response;
    }

    /**
     * Gestisce l'autenticazione JWT da geohub
     * Legge l'email dall'header X-Geohub-User-Email, cerca l'utente in osm2cai2 e genera un nuovo token
     * 
     * @param Request $request
     */
    private function handleGeohubAuthentication(Request $request): void
    {
        // Legge l'email dall'header specifico di geohub
        $userEmail = $request->header('X-Geohub-User-Email');
        
        if (!$userEmail) {
            Log::warning('No X-Geohub-User-Email header found');
            return;
        }

        Log::info('Processing geohub user email', ['email' => $userEmail]);

        try {
            // Cerca l'utente in osm2cai2 tramite email
            $user = User::where('email', $userEmail)->first();
            if (!$user) {
                Log::error('User not found in osm2cai2', ['email' => $userEmail]);
                return;
            }

            Log::info('Found user in osm2cai2', [
                'user_id' => $user->id,
                'email' => $user->email,
                'name' => $user->name
            ]);

            // Genera un nuovo token JWT per osm2cai2
            $newToken = JWTAuth::fromUser($user);
            
            Log::info('Generated new JWT token for osm2cai2', [
                'user_id' => $user->id,
                'new_token_preview' => substr($newToken, 0, 50) . '...'
            ]);

            // Sostituisce l'header Authorization con il nuovo token
            $request->headers->set('Authorization', 'Bearer ' . $newToken);
            
            Log::info('Authorization header updated with new osm2cai2 token');

        } catch (\Exception $e) {
            Log::error('Error processing geohub authentication', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Process the request body to replace app_id in various formats
     * 
     * @param Request $request
     * @param int $mappedAppId
     */
    private function processRequestBody(Request $request, int $mappedAppId): void
    {
        $content = $request->getContent();
        if (empty($content)) {
            Log::info('Request body is empty, skipping processing');
            return;
        }

        Log::info('Processing request body', [
            'content_length' => strlen($content),
            'content_preview' => substr($content, 0, 200) . (strlen($content) > 200 ? '...' : '')
        ]);

        $data = json_decode($content, true);
        if (!is_array($data)) {
            Log::warning('Request body is not valid JSON or not an array', [
                'json_error' => json_last_error_msg(),
                'decoded_type' => gettype($data)
            ]);
            return;
        }

        $modified = false;
        $changes = [];

        // Gestisci app_id nelle properties (formato standard UGC)
        if (isset($data['properties']['app_id'])) {
            $oldValue = $data['properties']['app_id'];
            $data['properties']['app_id'] = $mappedAppId;
            $modified = true;
            $changes[] = "properties.app_id: {$oldValue} -> {$mappedAppId}";
            Log::info('Updated app_id in properties', [
                'old_value' => $oldValue,
                'new_value' => $mappedAppId
            ]);
        }

        // Gestisci app_id nel campo feature (formato legacy)
        if (isset($data['feature'])) {
            $feature = is_string($data['feature']) ? json_decode($data['feature'], true) : $data['feature'];
            if (is_array($feature) && isset($feature['properties']['app_id'])) {
                $oldValue = $feature['properties']['app_id'];
                $feature['properties']['app_id'] = $mappedAppId;
                $data['feature'] = is_string($data['feature']) ? json_encode($feature) : $feature;
                $modified = true;
                $changes[] = "feature.properties.app_id: {$oldValue} -> {$mappedAppId}";
                Log::info('Updated app_id in feature properties', [
                    'old_value' => $oldValue,
                    'new_value' => $mappedAppId,
                    'feature_was_string' => is_string($data['feature'])
                ]);
            }
        }

        // Aggiorna il contenuto se è stato modificato
        if ($modified) {
            $request->setContent(json_encode($data));
            Log::info('Request body updated successfully', [
                'changes_made' => $changes,
                'new_content_length' => strlen($request->getContent())
            ]);
        } else {
            Log::info('No app_id found in request body, no changes made');
        }
    }

    /**
     * Mappa gli app ID da geohub a osm2cai
     * 
     * @param string|int $originalAppId
     * @return int
     */
    private function mapAppId($originalAppId): int
    {
        $mapping = [
            26 => 1, // it.webmapp.osm2cai -> osm2cai
            20 => 2, // it.webmapp.sicai -> sicai
            58 => 3, // it.webmapp.acquasorgente -> acquasorgente
        ];

        $originalAppId = (int) $originalAppId;
        $mappedAppId = $mapping[$originalAppId] ?? $originalAppId;
        
        Log::info('App ID mapping applied', [
            'input_app_id' => $originalAppId,
            'output_app_id' => $mappedAppId,
            'mapping_found' => isset($mapping[$originalAppId]),
            'mapping_rule' => isset($mapping[$originalAppId]) ? "{$originalAppId} -> {$mappedAppId}" : 'no mapping (using original)'
        ]);
        
        return $mappedAppId;
    }


} 
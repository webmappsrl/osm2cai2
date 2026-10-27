<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Services\UgcMediaHashService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Wm\WmPackage\Models\Abstracts\GeometryModel;
use Wm\WmPackage\Services\GeometryComputationService;

/**
 * STRATO 1 — DA CANCELLARE (oc:8867): copia locale destinata a essere cancellata.
 *
 * Porting di store(), findExistingByUuid() e fillModelWithRequest() di
 * Wm\WmPackage\Http\Controllers\Api\Abstracts\UgcController (oc:8718). Nel package la correzione
 * sta nella classe astratta; qui l'astratta non si tocca, quindi vive in un trait usato dai due
 * controller UGC locali. Va eliminato (insieme ai controller e al bind) quando wm-package verrà
 * aggiornato con la correzione.
 */
trait FindsExistingUgcByUuid
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateGeojson($request);

        $existing = $this->findExistingByUuid($validated);
        if ($existing) {
            // Retry dell'app con lo stesso uuid (oc:8718): si aggiorna il record. Le properties
            // ricevute vanno sopra quelle salvate, così restano le chiavi scritte dal server che
            // l'app non conosce (es. layer_id calcolato).
            $validated['properties'] = array_merge($existing->properties ?? [], $validated['properties']);
        }

        $model = $this->fillModelWithRequest($existing ?? $this->getModelIstance(), $request, $validated);

        $this->enrichUgcWithTaxonomyWhere($model);

        return response()->json(['id' => $model->id, 'message' => 'Created successfully'], 201);
    }

    /**
     * UGC già salvato con lo stesso properties.uuid. L'uuid si legge dai dati validati: l'app manda
     * un multipart con la feature in JSON nel campo `feature`, che `$request->input()` non decodifica.
     * Con duplicati già presenti si prende il più vecchio, cioè il padre del command di
     * normalizzazione (oc:8718).
     */
    protected function findExistingByUuid(array $validated): ?GeometryModel
    {
        $uuid = $validated['properties']['uuid'] ?? null;
        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        // Solo tra gli UGC dell'utente: l'uuid è pubblico nel link di condivisione
        // (/share/ugc-track/{uuid}), e un retry legittimo arriva sempre dallo stesso utente.
        return $this->getModelIstance()->newQuery()
            ->where('properties->uuid', $uuid)
            ->where('user_id', auth()->id())
            ->orderBy('id')
            ->first();
    }

    protected function fillModelWithRequest($model, $request, $validated)
    {
        $geometry = $validated['geometry'];
        $properties = $validated['properties'];

        $model = $model->fill([
            // validated in the validateProperties method
            'geometry' => GeometryComputationService::make()->getGeometryFromGeojsonRAW(json_encode($geometry)),
            'properties' => $properties,
            'name' => $properties['name'], // validated in the validateProperties method
            'app_id' => $properties['app_id'] ?? $model->app_id, // for those UGCs created from Nova, the app_id is not present in the properties, so we use the one from the model
            'created_by' => 'device',
        ]);

        try {
            $model->save();
        } catch (Exception $e) {
            $message = 'Error saving '.class_basename($this->getModelIstance()::class).'. '.$e->getMessage();
            Log::channel('ugc')->error($message);
            throw new Exception($message, 500);
        }

        if ($request->hasFile('images')) {
            $hashes = UgcMediaHashService::make();
            foreach ((array) $request->file('images') as $file) {
                $hash = $hashes->hashOfFile($file->getRealPath());
                // Immagine già presente (retry dell'app): non si salva.
                if ($hashes->findByContent($model, $hash)) {
                    continue;
                }
                $model->addMedia($file)
                    ->withCustomProperties([UgcMediaHashService::HASH_PROPERTY => $hash])
                    ->toMediaCollection('default');
            }
        }

        return $model;
    }
}

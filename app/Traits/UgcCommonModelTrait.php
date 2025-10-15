<?php

namespace App\Traits;

use App\Enums\ValidatedStatusEnum;
use App\Models\EcPoi;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Wm\WmPackage\Models\App;
use Wm\WmPackage\Models\TaxonomyPoiType;

trait UgcCommonModelTrait
{
    /**
     * Common casts for UGC models
     */
    protected function getCommonCasts(): array
    {
        return [
            'validation_date' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'properties' => 'array',
        ];
    }

    /**
     * Initialize common casts - call this in the boot method or constructor
     */
    protected function initializeUgcCommonModelTrait()
    {
        $this->casts = array_merge($this->casts ?? [], $this->getCommonCasts());
    }

    /**
     * Boot trait per gestire eventi comuni
     */
    protected static function bootUgcCommonModelTrait()
    {
        // Quando si crea un nuovo UGC, assicurati che abbia la struttura form
        static::creating(function ($ugc) {
            if (! $ugc->properties) {
                $ugc->properties = [];
            }

            if (! isset($ugc->created_by)) {
                $ugc->created_by = 'platform';
            }

            // Imposta automaticamente user_id se non è già impostato
            if (! isset($ugc->user_id) || $ugc->user_id === null) {
                if (Auth::check()) {
                    $ugc->user_id = Auth::id();
                }
            }

            $properties = $ugc->properties;

            // Se non esiste la struttura form, creala
            if (! isset($properties['form'])) {
                $properties['form'] = [
                    'id' => null,
                ];
                $ugc->properties = $properties;
            }
        });

        // Quando si salva un UGC, controlla se è stato validato
        static::saved(function ($ugc) {
            // Controlla se il campo validated è stato cambiato a VALID
            if ($ugc->isDirty('validated') && $ugc->validated === ValidatedStatusEnum::VALID->value) {
                $ugc->createEcPoiFromValidatedUgc();
            }
        });
    }

    /**
     * Relazione con l'autore (User locale)
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relazione con l'utente (User locale)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relazione con App del wmpackage
     */
    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class, 'app_id');
    }

    /**
     * Relazione con il validatore
     */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validator_id');
    }

    /**
     * Mutator per properties: crea automaticamente la struttura form se non esiste
     */
    public function setPropertiesAttribute($value)
    {
        // Assicurati che value sia un array
        $properties = is_array($value) ? $value : [];

        // Se non esiste la struttura form, creala
        if (! isset($properties['form'])) {
            $properties['form'] = [
                'id' => null, // Verrà impostato successivamente se necessario
            ];
        }

        // Se form esiste ma non ha un id, assicurati che abbia la struttura base
        if (isset($properties['form']) && ! isset($properties['form']['id'])) {
            $properties['form']['id'] = null;
        }

        $this->attributes['properties'] = json_encode($properties);
    }

    /**
     * Accessor to get the form data from properties
     */
    public function getFormAttribute()
    {
        return $this->properties['form'] ?? null;
    }

    /**
     * Accessor to get the form ID from properties
     */
    public function getFormIdAttribute()
    {
        return $this->properties['form']['id'] ?? null;
    }

    /**
     * Accessor per registered_at con logica di fallback
     */
    public function getRegisteredAtAttribute()
    {
        return isset($this->raw_data['date'])
            ? Carbon::parse($this->raw_data['date'])
            : $this->created_at;
    }

    /**
     * Converte un UGC validato in un EC POI
     */
    public function createEcPoiFromValidatedUgc()
    {
        if ($this->checkExistingEcPoi()) {
            return;
        }

        try {
            DB::beginTransaction();

            $ecPoi = $this->createEcPoi();
            $this->attachTaxonomyToEcPoi($ecPoi);
            $this->duplicateMediaToEcPoi($ecPoi);

            DB::commit();

            Log::info('✅ UGC convertito con successo in EC POI!', [
                'ugc_id' => $this->id,
                'ec_poi_id' => $ecPoi->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('❌ Errore durante la conversione UGC a EC POI', [
                'ugc_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Controlla se esiste già un EC POI per questo UGC
     */
    private function checkExistingEcPoi(): bool
    {
        $existingEcPoi = EcPoi::where('properties->ugc->ugc_poi_id', $this->id)->first();

        if ($existingEcPoi) {
            Log::warning('⚠️ EC POI già esistente per questo UGC', [
                'ugc_id' => $this->id,
                'ec_poi_id' => $existingEcPoi->id,
            ]);

            return true;
        }

        return false;
    }

    /**
     * Crea il nuovo EC POI
     */
    private function createEcPoi(): EcPoi
    {
        $properties = $this->prepareEcPoiProperties();
        $acquasorgenteApp = App::where('sku', 'it.webmapp.acquasorgente')->first();
        $appId = $acquasorgenteApp->id ?? $this->app_id;
        $userId = $acquasorgenteApp->user_id ?? Auth::user()->id;

        return EcPoi::create([
            'name' => $this->name ?? $this->properties['name'] ?? '',
            'geometry' => $this->geometry,
            'properties' => $properties,
            'app_id' => $appId,
            'user_id' => $userId,
            'type' => 'natural_spring',
            'score' => 1,
        ]);
    }

    /**
     * Prepara le proprietà per l'EC POI
     */
    private function prepareEcPoiProperties(): array
    {
        $ugcProperties = $this->extractUgcProperties();
        $rawData = $this->extractRawData();

        $properties = array_merge($ugcProperties, $rawData);

        // Aggiungi le informazioni UGC strutturate
        $properties['ugc'] = [
            'ugc_poi_id' => $this->id,
            'ugc_user_id' => $this->user_id,
            'conversion_date' => now()->toISOString(),
        ];

        unset($properties['uuid']);
        $properties['form']['index'] = 0;

        return $properties;
    }

    /**
     * Estrae le proprietà UGC
     */
    private function extractUgcProperties(): array
    {
        if (! $this->properties) {
            return [];
        }

        $properties = is_string($this->properties) ? json_decode($this->properties, true) : $this->properties;

        return is_array($properties) ? $properties : [];
    }

    /**
     * Estrae i raw data
     */
    private function extractRawData(): array
    {
        if (! $this->raw_data) {
            return [];
        }

        $rawData = is_string($this->raw_data) ? json_decode($this->raw_data, true) : $this->raw_data;

        return is_array($rawData) ? $rawData : [];
    }

    /**
     * Associa la taxonomy "water-point" all'EC POI
     */
    private function attachTaxonomyToEcPoi(EcPoi $ecPoi): void
    {
        $taxonomyPoiType = TaxonomyPoiType::where('identifier', 'water-monitoring')->first();

        if ($taxonomyPoiType) {
            $ecPoi->taxonomyPoiTypes()->attach($taxonomyPoiType->id);
        }
    }

    /**
     * Duplica i media dall'UGC all'EC POI
     */
    private function duplicateMediaToEcPoi(EcPoi $ecPoi): void
    {
        $medias = $this->media;

        if ($medias->count() === 0) {
            return;
        }

        foreach ($medias as $media) {
            try {
                // Usa il metodo copy() di MediaLibrary che gestisce automaticamente
                // la copia del file fisico e la generazione delle thumbnail
                $media->copy($ecPoi);
            } catch (\Exception $e) {
                Log::error('Error copying media {$media->id}: '.$e->getMessage());
            }
        }
    }
}

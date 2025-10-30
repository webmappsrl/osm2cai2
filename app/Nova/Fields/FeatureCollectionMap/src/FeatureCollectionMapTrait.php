<?php

namespace App\Nova\Fields\FeatureCollectionMap\src;

use Illuminate\Support\Facades\DB;

trait FeatureCollectionMapTrait
{
    /**
     * Array di features aggiuntive per la mappa
     */
    protected array $additionalFeaturesForMap = [];

    /**
     * Permette di aggiungere features custom alla mappa.
     * Ogni feature deve essere un array GeoJSON valido con properties validate.
     *
     * @param  array  $features  Array di features GeoJSON da aggiungere alla mappa
     *
     * @throws \InvalidArgumentException Se le features non sono valide
     */
    public function addFeaturesForMap(array $features): void
    {
        foreach ($features as $feature) {
            // Validazione struttura feature GeoJSON
            if (! isset($feature['type']) || $feature['type'] !== 'Feature') {
                throw new \InvalidArgumentException('Ogni elemento deve essere un Feature GeoJSON valido.');
            }

            // Validazione properties se presenti
            if (isset($feature['properties'])) {
                $this->validateWidgetProperties($feature['properties']);
            }

            $this->additionalFeaturesForMap[] = $feature;
        }
    }

    /**
     * Restituisce le features aggiuntive aggiunte tramite addFeaturesForMap
     */
    public function getAdditionalFeaturesForMap(): array
    {
        return $this->additionalFeaturesForMap;
    }

    /**
     * Pulisce le features aggiuntive
     */
    public function clearAdditionalFeaturesForMap(): void
    {
        $this->additionalFeaturesForMap = [];
    }

    /**
     * Valida le properties per il widget FeatureCollectionMap
     *
     * Properties supportate dal widget:
     *
     * LINEE/POLIGONI:
     * - strokeColor: Colore del bordo (es. 'blue', 'rgb(255,0,0)', 'rgba(255,0,0,0.8)')
     * - strokeWidth: Spessore del bordo (numero, es. 2, 6)
     * - fillColor: Colore di riempimento per poligoni (es. 'rgba(0,0,255,0.3)')
     *
     * PUNTI:
     * - pointStrokeColor: Colore del bordo del punto (es. 'rgb(255,255,255)')
     * - pointStrokeWidth: Spessore del bordo del punto (numero, es. 2)
     * - pointFillColor: Colore di riempimento del punto (es. 'rgba(255,0,0,0.8)')
     * - pointRadius: Raggio del punto (numero, es. 4, 6)
     *
     * INTERATTIVITÀ:
     * - tooltip: Testo mostrato al hover (stringa)
     * - link: URL per la navigazione al click (stringa)
     *
     * @param  array  $properties  Properties da validare
     * @return array Properties validate
     *
     * @throws \InvalidArgumentException Se ci sono properties non supportate
     */
    protected function validateWidgetProperties(array $properties): array
    {
        $validProperties = [
            // Properties per linee/poligoni
            'strokeColor',
            'strokeWidth',
            'fillColor',
            // Properties per punti
            'pointStrokeColor',
            'pointStrokeWidth',
            'pointFillColor',
            'pointRadius',
            // Properties per interattività
            'tooltip',
            'link',
        ];

        $invalidProperties = array_diff(array_keys($properties), $validProperties);

        if (! empty($invalidProperties)) {
            throw new \InvalidArgumentException(
                'Properties non supportate dal widget FeatureCollectionMap: '.
                implode(', ', $invalidProperties).'. '.
                'Properties valide: '.implode(', ', $validProperties)
            );
        }

        return $properties;
    }

    /**
     * Get track as GeoJSON feature collection for map widget
     *
     * @param  array  $properties  Properties validate per la feature principale
     * @return array GeoJSON feature collection
     */
    public function getFeatureCollectionMapFromTrait($properties = []): array
    {
        // Valida le properties prima di passarle a getFeatureMap
        $validatedProperties = $this->validateWidgetProperties($properties);

        if (! $this->geometry) {
            return [
                'type' => 'FeatureCollection',
                'features' => $this->additionalFeaturesForMap,
            ];
        }

        // Converti WKB in GeoJSON usando PostGIS
        $geojson = DB::select("SELECT ST_AsGeoJSON(ST_GeomFromWKB(decode(?, 'hex'))) as geojson", [$this->geometry]);

        if (empty($geojson)) {
            return [
                'type' => 'FeatureCollection',
                'features' => $this->additionalFeaturesForMap,
            ];
        }

        $feature = $this->getFeatureMap(null, $validatedProperties);

        return [
            'type' => 'FeatureCollection',
            'features' => array_merge([$feature], $this->additionalFeaturesForMap),
        ];
    }

    /**
     * Restituisce un feature GeoJSON per il widget mappa con validazione delle properties
     *
     * @param  string|null  $geometry  Geometria WKB (hex), se null usa $this->geometry
     * @param  array  $properties  Properties validate per il widget mappa
     * @return array Feature GeoJSON con properties validate
     *
     * @throws \InvalidArgumentException Se vengono passate properties non supportate
     */
    public function getFeatureMap($geometry = null, array $properties = [])
    {
        if ($geometry === null) {
            $geometry = $this->geometry;
        }

        // Valida le properties prima di utilizzarle
        $validatedProperties = $this->validateWidgetProperties($properties);

        $geojson = DB::select("SELECT ST_AsGeoJSON(ST_GeomFromWKB(decode(?, 'hex'))) as geojson", [$geometry]);
        $geometry = json_decode($geojson[0]->geojson, true);

        return [
            'type' => 'Feature',
            'geometry' => $geometry,
            'properties' => $validatedProperties,
        ];
    }
}

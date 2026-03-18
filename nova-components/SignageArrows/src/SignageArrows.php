<?php

namespace Osm2cai\SignageArrows;

use Laravel\Nova\Fields\Field;

/**
 * SignageArrows - Campo Nova per la visualizzazione delle frecce segnaletica CAI
 *
 * Visualizza le frecce forward (destra) e backward (sinistra) con i dati
 * della segnaletica. Accetta attributi in dot-notation (es. "properties.signage")
 */
class SignageArrows extends Field
{
    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'signage-arrows';

    /**
     * Create a new field.
     *
     * @param  string  $name
     * @param  string|callable|null  $attribute
     * @return void
     */
    public function __construct($name, $attribute = null, ?callable $resolveCallback = null)
    {
        parent::__construct($name, $attribute, $resolveCallback);

        $this->onlyOnDetail();
    }

    /**
     * Resolve the field's value for display.
     *
     * @param  mixed  $resource
     */
    protected function resolveAttribute($resource, string $attribute): mixed
    {
        $signageData = data_get($resource, $attribute) ?? [];

        if (empty($signageData)) {
            return $signageData;
        }

        $hasWrapper = isset($signageData['signage']);
        $routeKeys  = array_keys($hasWrapper ? $signageData['signage'] : $signageData);

        foreach ($routeKeys as $routeId) {
            if ($routeId === 'arrow_order') {
                continue;
            }

            $routeData = $hasWrapper
                ? ($signageData['signage'][$routeId] ?? null)
                : ($signageData[$routeId] ?? null);


            if (!is_array($routeData) || !isset($routeData['arrows'])) {
                continue;
            }

            $hikingRoute = \App\Models\HikingRoute::find((int) $routeId);
            if (!$hikingRoute) {
                continue;
            }

            $hrSignage         = $hikingRoute->properties['signage'] ?? [];
            $checkpointOrder   = array_map('intval', $hrSignage['checkpoint_order'] ?? []);
            $activeCheckpoints = array_map('intval', $hrSignage['checkpoint'] ?? []);
            $activeSet         = array_flip($activeCheckpoints);

            if (empty($checkpointOrder)) {
                continue;
            }

            $poleNames = \App\Models\Poles::whereIn('id', $checkpointOrder)
                ->get(['id', 'name', 'ref', 'properties'])
                ->keyBy('id');

            foreach ($routeData['arrows'] as $arrowIdx => $arrow) {
                if (!isset($arrow['rows']) || count($arrow['rows']) < 3) {
                    $routeData['arrows'][$arrowIdx]['available_midpoints'] = [];
                    continue;
                }

                $nearestId  = (int) ($arrow['rows'][0]['id'] ?? 0);
                $finalId    = (int) ($arrow['rows'][count($arrow['rows']) - 1]['id'] ?? 0);
                $nearestPos = array_search($nearestId, $checkpointOrder);
                $finalPos   = array_search($finalId, $checkpointOrder);

                if ($nearestPos === false || $finalPos === false) {
                    $routeData['arrows'][$arrowIdx]['available_midpoints'] = [];
                    continue;
                }

                $start     = min($nearestPos, $finalPos);
                $end       = max($nearestPos, $finalPos);
                $midpoints = [];

                for ($k = $start + 1; $k < $end; $k++) {
                    $midId = $checkpointOrder[$k];

                    if ($midId === $nearestId || $midId === $finalId) {
                        continue;
                    }
                    if (!isset($activeSet[$midId])) {
                        continue;
                    }

                    $midData     = $arrow['midpoints_data'][(string) $midId] ?? [];
                    $pole        = $poleNames->get($midId);
                    $poleName = $pole?->properties['name'] ?? $pole?->name ?? '';
                    $midpoints[] = array_merge([
                        'id'          => $midId,
                        'name'        => $poleName,
                        'ref'         => $pole?->ref ?? '',
                        'description' => '',
                    ], $midData);
                }

                $routeData['arrows'][$arrowIdx]['available_midpoints'] = $midpoints;
            }

            // Scrive il routeData aggiornato direttamente in $signageData
            if ($hasWrapper) {
                $signageData['signage'][$routeId] = $routeData;
            } else {
                $signageData[$routeId] = $routeData;
            }
        }

        return $signageData;
    }
}

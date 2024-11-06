<?php

namespace App\Traits;


trait UGCFeatureCollectionTrait
{

    public function getUGCFeatureCollection($features)
    {
        $featureCollection = [
            "type" => "FeatureCollection",
            "features" => []
        ];

        if ($features) {
            foreach ($features as $feature) {
                $featureCollection["features"][] = $feature->getGeojson();
            }
        }

        return response()->json($featureCollection);
    }
}

<?php

namespace App\Models;

use App\Observers\TrailSurveyObserver;
use App\Traits\GeneratesPdfTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TrailSurvey extends Model
{
    use GeneratesPdfTrait;
    protected $fillable = [
        'hiking_route_id',
        'owner_id',
        'start_date',
        'end_date',
        'description',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function hikingRoute(): BelongsTo
    {
        return $this->belongsTo(HikingRoute::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id');
    }

    public function ugcPois(): BelongsToMany
    {
        return $this->belongsToMany(UgcPoi::class, 'trail_survey_ugc_poi');
    }

    public function ugcTracks(): BelongsToMany
    {
        return $this->belongsToMany(UgcTrack::class, 'trail_survey_ugc_track');
    }

    protected static function booted(): void
    {
        self::observe(TrailSurveyObserver::class);
    }

    /**
     * Get FeatureCollection GeoJSON combining ugcPois and ugcTracks
     * Each feature has properties with model_type and model_id for synchronization
     *
     * @return array
     */
    public function getFeatureCollectionForGrid(): array
    {
        $features = [];

        // Load relations if not already loaded
        if (!$this->relationLoaded('hikingRoute')) {
            $this->load('hikingRoute');
        }
        if (!$this->relationLoaded('ugcPois')) {
            $this->load('ugcPois.user');
        } else {
            // If already loaded, eager load user relation
            $this->ugcPois->loadMissing('user');
        }
        if (!$this->relationLoaded('ugcTracks')) {
            $this->load('ugcTracks.user');
        } else {
            // If already loaded, eager load user relation
            $this->ugcTracks->loadMissing('user');
        }

        // Color palette excluding red (#d62728 and similar reds)
        $colorPalette = [
            '#1f77b4', // blue
            '#ff7f0e', // orange
            '#2ca02c', // green
            '#9467bd', // purple
            '#8c564b', // brown
            '#e377c2', // pink
            '#7f7f7f', // gray
            '#bcbd22', // olive
            '#17becf', // cyan
            '#aec7e8', // light blue
            '#ffbb78', // light orange
            '#98df8a', // light green
        ];
        $userColors = [];
        $currentColorIndex = 0;

        $getColorForUser = function (?string $userName = null) use (&$userColors, &$currentColorIndex, $colorPalette): string {
            $key = $userName ? mb_strtolower($userName) : 'unknown';

            if (! isset($userColors[$key])) {
                $userColors[$key] = $colorPalette[$currentColorIndex % count($colorPalette)];
                $currentColorIndex++;
            }

            return $userColors[$key];
        };

        // Add Hiking Route main geometry in RED
        if ($this->hikingRoute && $this->hikingRoute->geometry) {
            $hikingRouteGeojson = $this->hikingRoute->getGeojson();
            if ($hikingRouteGeojson && isset($hikingRouteGeojson['type']) && $hikingRouteGeojson['type'] === 'Feature') {
                // Set red color for hiking route
                $hikingRouteGeojson['properties']['strokeColor'] = 'rgba(255, 0, 0, 1)';
                $hikingRouteGeojson['properties']['strokeWidth'] = 4;
                $hikingRouteGeojson['properties']['model_type'] = 'HikingRoute';
                $hikingRouteGeojson['properties']['model_id'] = $this->hikingRoute->id;
                $hikingRouteGeojson['properties']['link'] = url("/resources/hiking-routes/{$this->hikingRoute->id}");
                $hikingRouteGeojson['properties']['tooltip'] = $this->hikingRoute->name ?? "Hiking Route #{$this->hikingRoute->id}";
                // Add at the beginning so it appears first
                array_unshift($features, $hikingRouteGeojson);
            }
        }

        // Add UgcPois
        foreach ($this->ugcPois as $poi) {
            $geojson = $poi->getGeojson();
            if ($geojson && isset($geojson['type']) && $geojson['type'] === 'Feature') {
                // Add model_type and model_id to properties
                $geojson['properties']['model_type'] = 'UgcPoi';
                $geojson['properties']['model_id'] = $poi->id;
                // Add link to open resource in new tab
                $geojson['properties']['link'] = url("/resources/ugc-pois/{$poi->id}");
                // Add tooltip with user name, form type and title for hover display
                $title = $poi->name ?? "POI #{$poi->id}";
                $userName = $poi->user->name ?? null;
                $formId = $poi->form_id ?? null;
                $color = $getColorForUser($userName);

                $tooltipParts = array_filter([$userName, $formId, $title]);
                $geojson['properties']['tooltip'] = !empty($tooltipParts)
                    ? implode(' - ', $tooltipParts)
                    : $title;
                $geojson['properties']['pointFillColor'] = $color;
                $geojson['properties']['pointStrokeColor'] = $color;
                $geojson['properties']['pointStrokeWidth'] = 2;
                $features[] = $geojson;
            }
        }

        // Add UgcTracks
        foreach ($this->ugcTracks as $track) {
            $geojson = $track->getGeojson();
            if ($geojson && isset($geojson['type']) && $geojson['type'] === 'Feature') {
                // Add model_type and model_id to properties
                $geojson['properties']['model_type'] = 'UgcTrack';
                $geojson['properties']['model_id'] = $track->id;
                // Add link to open resource in new tab
                $geojson['properties']['link'] = url("/resources/ugc-tracks/{$track->id}");
                // Add tooltip with user name, form type and title for hover display
                $title = $track->name ?? "Track #{$track->id}";
                $userName = $track->user->name ?? null;
                $formId = $track->form_id ?? null;
                $color = $getColorForUser($userName);

                $tooltipParts = array_filter([$userName, $formId, $title]);
                $geojson['properties']['tooltip'] = !empty($tooltipParts)
                    ? implode(' - ', $tooltipParts)
                    : $title;
                $geojson['properties']['strokeColor'] = $color;
                $geojson['properties']['strokeWidth'] = 3;
                $features[] = $geojson;
            }
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }

    /**
     * Override the methods of the GeneratesPdfTrait for TrailSurvey
     */
    public function getPdfViewName(): string
    {
        return 'trail-survey.pdf';
    }

    public function getPdfViewVariableName(): string
    {
        return 'trailSurvey';
    }

    public function getPdfPath(): string
    {
        $ownerName = $this->owner ? $this->sanitizeFileName($this->owner->name) : 'unknown';
        $startDate = $this->start_date ? $this->start_date->format('Ymd') : 'nodate';
        $endDate = $this->end_date ? $this->end_date->format('Ymd') : 'nodate';

        return "trail-surveys/{$this->id}/survey_{$ownerName}_{$startDate}_{$endDate}.pdf";
    }

    public function getPdfRelationsToLoad(): array
    {
        return ['hikingRoute', 'owner', 'ugcPois', 'ugcTracks'];
    }

    public function getPdfControllerClass(): ?string
    {
        return \App\Http\Controllers\TrailSurveyPdfController::class;
    }

    /**
     * Get all unique participant names from associated UGC POIs and Tracks
     *
     * @return array
     */
    public function getParticipants(): array
    {
        $participants = [];

        // Load UGC POIs with users if not already loaded
        if (!$this->relationLoaded('ugcPois')) {
            $this->load('ugcPois.user');
        } else {
            $this->ugcPois->loadMissing('user');
        }

        // Load UGC Tracks with users if not already loaded
        if (!$this->relationLoaded('ugcTracks')) {
            $this->load('ugcTracks.user');
        } else {
            $this->ugcTracks->loadMissing('user');
        }

        // Collect user names from POIs
        foreach ($this->ugcPois as $poi) {
            if ($poi->user && $poi->user->name) {
                $participants[$poi->user->id] = $poi->user->name;
            }
        }

        // Collect user names from Tracks
        foreach ($this->ugcTracks as $track) {
            if ($track->user && $track->user->name) {
                $participants[$track->user->id] = $track->user->name;
            }
        }

        // Return unique names sorted alphabetically
        return array_values(array_unique($participants));
    }
}

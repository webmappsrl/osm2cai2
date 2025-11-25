<?php

namespace App\Models;

use App\Observers\TrailSurveyObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TrailSurvey extends Model
{
    protected $fillable = [
        'hiking_route_id',
        'owner_id',
        'start_date',
        'end_date',
        'description',
        'pdf_url',
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
}

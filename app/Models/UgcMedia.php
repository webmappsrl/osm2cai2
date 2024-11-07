<?php

namespace App\Models;

use App\Models\User;
use App\Models\UgcPoi;
use App\Traits\SpatialDataTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UgcMedia extends Model
{
    use HasFactory, SpatialDataTrait;

    protected $fillable = ['geohub_id', 'name', 'description', 'geometry', 'user_id', 'updated_at', 'raw_data', 'taxonomy_wheres', 'relative_url', 'app_id', 'ugc_poi_id', 'ugc_track_id'];

    protected $casts = [
        'raw_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ugc_poi(): BelongsTo
    {
        return $this->belongsTo(UgcPoi::class);
    }

    public function ugc_track(): BelongsTo
    {
        return $this->belongsTo(UgcTrack::class);
    }

    /**
     * Return the json version of the ugc media, avoiding the geometry
     *
     * @return array
     */
    public function getJsonProperties(): array
    {
        $array = $this->toArray();

        $propertiesToClear = ['geometry'];
        foreach ($array as $property => $value) {
            if (is_null($value) || in_array($property, $propertiesToClear))
                unset($array[$property]);

            if ($property == 'relative_url') {
                if (Storage::disk('public')->exists($value))
                    $array['url'] = Storage::disk('public')->url($value);
                unset($array[$property]);
            }

            if (isset($array['raw_data'])) {
                $array['raw_data']  = json_encode($array['raw_data']);
            }
        }

        return $array;
    }

    /**
     * Create a geojson from the ugc media
     *
     * @return array
     */
    public function getGeojson(): ?array
    {
        $feature = $this->getEmptyGeojson();
        if (isset($feature["properties"])) {
            $feature["properties"] = $this->getJsonProperties();

            return $feature;
        } else return null;
    }

    public function getUrl()
    {
        if (Storage::disk('public')->exists($this->relative_url))
            return Storage::disk('public')->url($this->relative_url);
        return $this->relative_url;
    }
}

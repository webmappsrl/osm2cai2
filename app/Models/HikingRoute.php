<?php

namespace App\Models;

use App\Traits\TagsMappingTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Wm\WmOsmfeatures\Traits\OsmfeaturesImportableTrait;

class HikingRoute extends Model
{
    use HasFactory, TagsMappingTrait, OsmfeaturesImportableTrait;

    protected $fillable = [
        'geometry',
        'osmfeatures_id',
        'osmfeatures_data',
        'osmfeatures_updated_at',
    ];
}

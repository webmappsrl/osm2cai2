<?php

namespace Database\Factories;

use App\Models\UgcMedia;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UgcMediaFactory extends Factory
{
    protected $model = UgcMedia::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'geometry' => DB::raw("ST_GeomFromText('POINT(".$this->faker->longitude.' '.$this->faker->latitude.")')"),
            'app_id' => 'geohub_test_app_'.Str::random(5),
            'relative_url' => 'test/path/'.Str::random(10).'.jpg',
            'raw_data' => ['test_key' => 'test_value'],
        ];
    }
}

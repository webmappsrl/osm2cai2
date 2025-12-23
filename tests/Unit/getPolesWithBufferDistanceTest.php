<?php

namespace Tests\Unit;

use App\Models\HikingRoute;
use App\Models\Poles;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class getPolesWithBufferDistanceTest extends TestCase
{
    use DatabaseTransactions;

    private function createHikingRouteWithGeometry(string $geometry)
    {
        // Convert geometry to 3D format for hiking_routes (which requires Z dimension)
        $geometry3D = $this->convertTo3D($geometry);

        return HikingRoute::createQuietly([
            'geometry' => DB::raw("ST_GeomFromText('{$geometry3D}', 4326)"),
            'geometry_raw_data' => DB::raw("ST_GeomFromText('{$geometry3D}', 4326)"),
            'is_geometry_correct' => true,
        ]);
    }

    private function createPoleWithGeometry($geometry)
    {
        // Poles table does NOT require Z dimension, so keep geometry as 2D
        return Poles::createQuietly([
            'geometry' => DB::raw("ST_GeomFromText('{$geometry}', 4326)"),
        ]);
    }

    private function convertTo3D(string $geometry): string
    {
        // If already 3D (contains Z), return as is
        if (preg_match('/[Zz]/', $geometry)) {
            return $geometry;
        }

        // Convert MULTILINESTRING to MULTILINESTRINGZ
        if (preg_match('/MULTILINESTRING\s*\(/', $geometry)) {
            $geometry = preg_replace('/MULTILINESTRING\s*\(/', 'MULTILINESTRINGZ(', $geometry);
            // Add Z coordinate (0) to all coordinates
            $geometry = preg_replace('/(\d+\.?\d*)\s+(\d+\.?\d*)(?=\s*[,\)])/', '$1 $2 0', $geometry);

            return $geometry;
        }

        return $geometry;
    }

    /** @test */
    public function it_returns_poles_within_buffer_distance()
    {
        $hikingRoute = $this->createHikingRouteWithGeometry('MULTILINESTRING(
            (14.0000 37.0000, 14.0010 37.0010, 14.0020 37.0020),
            (14.0020 37.0020, 14.0030 37.0030)
            )'
        );
        $nearPole = $this->createPoleWithGeometry('POINT(14.0011 37.0011)');
        $nearPoleId = $nearPole->id;

        $farPole = $this->createPoleWithGeometry('POINT(15.0354462 37.8025841)');
        $farPoleId = $farPole->id;

        $result = $hikingRoute->getPolesWithBuffer(10);
        $resultId = $result->pluck('id')->toArray();

        $this->assertContains($nearPoleId, $resultId);
        $this->assertNotContains($farPoleId, $resultId);
        $this->assertCount(1, $result);
        $this->assertTrue($result->contains($nearPole));
        $this->assertFalse($result->contains($farPole));
    }

    /** @test */
    public function it_returns_empty_collection_if_no_poles_within_buffer()
    {
        $hikingRoute = $this->createHikingRouteWithGeometry('MULTILINESTRING(
            (14.0000 37.0000, 14.0010 37.0010, 14.0020 37.0020),
            (14.0020 37.0020, 14.0030 37.0030)
            )');

        $farPole = $this->createPoleWithGeometry('POINT(15.0354462 37.8025841)');

        $result = $hikingRoute->getPolesWithBuffer(10);

        $this->assertFalse($result->contains($farPole));
        $this->assertCount(0, $result);
        $this->assertTrue($result->isEmpty());
    }
}

<?php

namespace Tests\Api;

use App\Models\Area;
use App\Models\CaiHut;
use App\Models\Club;
use App\Models\EcPoi;
use App\Models\HikingRoute;
use App\Models\Itinerary;
use App\Models\MountainGroups;
use App\Models\NaturalSpring;
use App\Models\Sector;
use App\Models\UgcMedia;
use App\Models\UgcPoi;
use App\Models\UgcTrack;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExportControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable throttling for testing
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function testListEndpoint(string $endpoint, string $modelClass): void
    {
        if ($modelClass === User::class) {
            $modelClass::factory()->count(3)->sequence(
                ['email' => 'user1@example.com'],
                ['email' => 'user2@example.com'],
                ['email' => 'user3@example.com']
            )->create();
        } elseif ($modelClass === HikingRoute::class) {
            $modelClass::factory()->count(3)->create([
                'geometry' => '{"type":"LineString","coordinates":[[10,10],[20,20],[30,30]]}',
            ]);
        } elseif ($modelClass === UgcTrack::class) {
            $modelClass::factory()->create([
                'geometry' => '{"type":"LineString","coordinates":[[10,10, 0],[20,20, 0],[30,30, 0]]}',
            ]);
        } elseif ($modelClass === Sector::class) {
            $modelClass::factory()->count(3)->sequence(
                ['name' => 'Sector 1', 'code' => 'T', 'full_code' => 'T123', 'num_expected' => 1234, 'geometry' => '{"type":"MultiPolygon","coordinates":[[[[10,10],[20,20],[30,30],[10,10]]]]}'],
                ['name' => 'Sector 2', 'code' => 'T', 'full_code' => 'T123', 'num_expected' => 1234, 'geometry' => '{"type":"MultiPolygon","coordinates":[[[[10,10],[20,20],[30,30],[10,10]]]]}'],
                ['name' => 'Sector 3', 'code' => 'T', 'full_code' => 'T123', 'num_expected' => 1234, 'geometry' => '{"type":"MultiPolygon","coordinates":[[[[10,10],[20,20],[30,30],[10,10]]]]}'],
            )->create();
        } elseif ($modelClass === Club::class) {
            $modelClass::factory()->count(3)->sequence(
                ['name' => 'Club 1', 'cai_code' => '92'],
                ['name' => 'Club 2', 'cai_code' => '93'],
                ['name' => 'Club 3', 'cai_code' => '94']
            )->create();
        } elseif ($modelClass === MountainGroups::class) {
            $modelClass::factory()->count(3)->sequence(
                ['name' => 'Mountain Group 1', 'geometry' => '{"type":"MultiPolygon","coordinates":[[[[10,10],[20,20],[30,30],[10,10]]]]}'],
                ['name' => 'Mountain Group 2', 'geometry' => '{"type":"MultiPolygon","coordinates":[[[[10,10],[20,20],[30,30],[10,10]]]]}'],
                ['name' => 'Mountain Group 3', 'geometry' => '{"type":"MultiPolygon","coordinates":[[[[10,10],[20,20],[30,30],[10,10]]]]}'],
            )->create();
        } elseif ($modelClass === CaiHut::class) {
            $modelClass::factory()->count(3)->sequence(
                ['name' => 'Cai Hut 1', 'geometry' => '{"type":"Point","coordinates":[10,10]}'],
                ['name' => 'Cai Hut 2', 'geometry' => '{"type":"Point","coordinates":[20,20]}'],
                ['name' => 'Cai Hut 3', 'geometry' => '{"type":"Point","coordinates":[30,30]}']
            )->create();
        } elseif ($modelClass === Area::class) {
            $modelClass::factory()->count(3)->sequence(
                ['name' => 'Area 1', 'code' => 'T', 'full_code' => 'T123', 'num_expected' => 1234, 'geometry' => '{"type":"Polygon","coordinates":[[[10,10],[20,20],[30,30],[10,10]]]}'],
                ['name' => 'Area 2', 'code' => 'T', 'full_code' => 'T123', 'num_expected' => 1234, 'geometry' => '{"type":"Polygon","coordinates":[[[10,10],[20,20],[30,30],[10,10]]]}'],
                ['name' => 'Area 3', 'code' => 'T', 'full_code' => 'T123', 'num_expected' => 1234, 'geometry' => '{"type":"Polygon","coordinates":[[[10,10],[20,20],[30,30],[10,10]]]}'],
            )->create();
        } else {
            $modelClass::factory()->count(3)->create();
        }

        $response = $this->getJson($endpoint);
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertIsArray($data);

        foreach ($data as $id => $updatedAt) {
            $this->assertIsInt($id);
            $this->assertIsString($updatedAt);
        }
    }

    private function testSingleFeatureEndpoint(string $endpoint, string $modelClass): void
    {
        if ($modelClass === User::class) {
            $model = $modelClass::factory()->create(['email' => 'user@example.com']);
        } elseif ($modelClass === HikingRoute::class) {
            $model = $modelClass::factory()->create([
                'geometry' => '{"type":"LineString","coordinates":[[10,10],[20,20],[30,30]]}',
            ]);
        } elseif ($modelClass === UgcTrack::class) {
            $model = $modelClass::factory()->create([
                'geometry' => '{"type":"LineString","coordinates":[[10,10, 0],[20,20, 0],[30,30, 0]]}',
            ]);
        } elseif ($modelClass === Sector::class) {
            $model = $modelClass::factory()->create([
                'name' => 'Test Sector',
                'code' => 'T',
                'full_code' => 'T123',
                'num_expected' => 1234,
                'geometry' => '{"type":"MultiPolygon","coordinates":[[[[10,10],[20,20],[30,30],[10,10]]]]}',
            ]);
        } elseif ($modelClass === Club::class) {
            $model = $modelClass::factory()->create(['name' => 'Test Club', 'cai_code' => '95']);
        } elseif ($modelClass === MountainGroups::class) {
            $model = $modelClass::factory()->create(['name' => 'Test Mountain Group', 'geometry' => '{"type":"MultiPolygon","coordinates":[[[[10,10],[20,20],[30,30],[10,10]]]]}']);
        } elseif ($modelClass === CaiHut::class) {
            $model = $modelClass::factory()->create(['name' => 'Test Cai Hut', 'geometry' => '{"type":"Point","coordinates":[10,10]}']);
        } elseif ($modelClass === Area::class) {
            $model = $modelClass::factory()->create(
                ['name' => 'Test Area', 'code' => 'T', 'full_code' => 'T123', 'num_expected' => 1234, 'geometry' => '{"type":"Polygon","coordinates":[[[10,10],[20,20],[30,30],[10,10]]]}'],
            );
        } else {
            $model = $modelClass::factory()->create();
        }

        $response = $this->getJson($endpoint.'/'.$model->id);
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertArrayHasKey('data', $data);

        $data = $data['data'];
        $this->assertEquals($model->id, $data['id']);

        $columns = Schema::getColumnListing((new $modelClass)->getTable());
        foreach ($columns as $column) {
            if ($column === 'remember_token') {
                continue;
            }
            $this->assertArrayHasKey($column, $data);
        }
    }

    public function testHikingRoutesList(): void
    {
        $this->testListEndpoint('/api/v2/export/hiking-routes/list', HikingRoute::class);
    }

    public function testHikingRoutesSingleFeature(): void
    {
        $this->testSingleFeatureEndpoint('/api/v2/export/hiking-routes', HikingRoute::class);
    }

    public function testUsersList(): void
    {
        $this->testListEndpoint('/api/v2/export/users/list', User::class);
    }

    public function testUsersSingleFeature(): void
    {
        $this->testSingleFeatureEndpoint('/api/v2/export/users', User::class);
    }

    public function testUgcPoisList(): void
    {
        $this->testListEndpoint('/api/v2/export/ugc_pois/list', UgcPoi::class);
    }

    public function testUgcPoisSingleFeature(): void
    {
        $this->testSingleFeatureEndpoint('/api/v2/export/ugc_pois', UgcPoi::class);
    }

    public function testUgcTracksList(): void
    {
        $this->testListEndpoint('/api/v2/export/ugc_tracks/list', UgcTrack::class);
    }

    public function testUgcTracksSingleFeature(): void
    {
        $this->testSingleFeatureEndpoint('/api/v2/export/ugc_tracks', UgcTrack::class);
    }

    public function testUgcMediasList(): void
    {
        $this->testListEndpoint('/api/v2/export/ugc_media/list', UgcMedia::class);
    }

    public function testUgcMediasSingleFeature(): void
    {
        $this->testSingleFeatureEndpoint('/api/v2/export/ugc_media', UgcMedia::class);
    }

    public function testAreasList(): void
    {
        $this->testListEndpoint('/api/v2/export/areas/list', Area::class);
    }

    public function testAreasSingleFeature(): void
    {
        $this->testSingleFeatureEndpoint('/api/v2/export/areas', Area::class);
    }

    public function testSectorsList(): void
    {
        $this->testListEndpoint('/api/v2/export/sectors/list', Sector::class);
    }

    public function testSectorsSingleFeature(): void
    {
        $this->testSingleFeatureEndpoint('/api/v2/export/sectors', Sector::class);
    }

    public function testClubsList(): void
    {
        $this->testListEndpoint('/api/v2/export/sections/list', Club::class);
    }

    public function testClubsSingleFeature(): void
    {
        $this->testSingleFeatureEndpoint('/api/v2/export/sections', Club::class);
    }

    public function testMountainGroupsList(): void
    {
        $this->testListEndpoint('/api/v2/export/mountain_groups/list', MountainGroups::class);
    }

    public function testMountainGroupsSingleFeature(): void
    {
        $this->testSingleFeatureEndpoint('/api/v2/export/mountain_groups', MountainGroups::class);
    }

    public function testNaturalSpringsList(): void
    {
        $this->testListEndpoint('/api/v2/export/natural_springs/list', NaturalSpring::class);
    }

    public function testNaturalSpringsSingleFeature(): void
    {
        $this->testSingleFeatureEndpoint('/api/v2/export/natural_springs', NaturalSpring::class);
    }

    public function testItinerariesList(): void
    {
        $this->testListEndpoint('/api/v2/export/itineraries/list', Itinerary::class);
    }

    public function testItinerariesSingleFeature(): void
    {
        $this->testSingleFeatureEndpoint('/api/v2/export/itineraries', Itinerary::class);
    }

    public function testHutsList(): void
    {
        $this->testListEndpoint('/api/v2/export/huts/list', CaiHut::class);
    }

    public function testHutsSingleFeature(): void
    {
        $this->testSingleFeatureEndpoint('/api/v2/export/huts', CaiHut::class);
    }

    public function testEcPoisList(): void
    {
        $this->testListEndpoint('/api/v2/export/ec_pois/list', EcPoi::class);
    }

    public function testEcPoisSingleFeature(): void
    {
        $this->testSingleFeatureEndpoint('/api/v2/export/ec_pois', EcPoi::class);
    }
}

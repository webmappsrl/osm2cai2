<?php

namespace Tests\Unit;

use App\Jobs\CheckNearbyEcPoisJob;
use App\Models\HikingRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CheckNearbyEcPoisJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test che il job viene saltato per app diverse da osm2cai
     */
    public function test_job_skipped_for_non_osm2cai_app()
    {
        // Mock del metodo isOsm2caiApp per restituire false
        $job = $this->getMockBuilder(CheckNearbyEcPoisJob::class)
            ->setConstructorArgs([new HikingRoute, 1000])
            ->onlyMethods(['isOsm2caiApp'])
            ->getMock();

        $job->expects($this->once())
            ->method('isOsm2caiApp')
            ->willReturn(false);

        // Mock del log per verificare che viene chiamato
        Log::shouldReceive('info')
            ->with('Checking nearby ec_pois for hiking_routes 0')
            ->once();

        Log::shouldReceive('info')
            ->with('Job skipped: not running on osm2cai app')
            ->once();

        // Esegui il job
        $job->handle();

        // Se arriviamo qui senza eccezioni, il test è passato
        $this->assertTrue(true);
    }

    /**
     * Test che il job viene eseguito per app osm2cai
     */
    public function test_job_executed_for_osm2cai_app()
    {
        // Mock del metodo isOsm2caiApp per restituire true
        $job = $this->getMockBuilder(CheckNearbyEcPoisJob::class)
            ->setConstructorArgs([new HikingRoute, 1000])
            ->onlyMethods(['isOsm2caiApp'])
            ->getMock();

        $job->expects($this->once())
            ->method('isOsm2caiApp')
            ->willReturn(true);

        // Mock del log per verificare che viene chiamato
        Log::shouldReceive('info')
            ->with('Checking nearby ec_pois for hiking_routes 0')
            ->once();

        // Mock del modello per simulare che non ha geometria
        $mockModel = $this->createMock(HikingRoute::class);
        $mockModel->expects($this->once())
            ->method('getAttribute')
            ->with('geometry')
            ->willReturn(null);

        $mockModel->expects($this->once())
            ->method('getTable')
            ->willReturn('hiking_routes');

        // Crea un nuovo job con il mock
        $job = $this->getMockBuilder(CheckNearbyEcPoisJob::class)
            ->setConstructorArgs([$mockModel, 1000])
            ->onlyMethods(['isOsm2caiApp'])
            ->getMock();

        $job->expects($this->once())
            ->method('isOsm2caiApp')
            ->willReturn(true);

        Log::shouldReceive('warning')
            ->with('hiking_routes 0 has no geometry')
            ->once();

        // Esegui il job
        $job->handle();

        // Se arriviamo qui senza eccezioni, il test è passato
        $this->assertTrue(true);
    }
}

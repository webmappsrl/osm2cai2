<?php

namespace Tests\Feature\Ugc;

use Tests\TestCase;

class UgcControllerBindingTest extends TestCase
{
    public function test_il_container_risolve_i_controller_ugc_locali(): void
    {
        $this->assertInstanceOf(
            \App\Http\Controllers\Api\UgcPoiController::class,
            app(\Wm\WmPackage\Http\Controllers\Api\UgcPoiController::class)
        );
        $this->assertInstanceOf(
            \App\Http\Controllers\Api\UgcTrackController::class,
            app(\Wm\WmPackage\Http\Controllers\Api\UgcTrackController::class)
        );
    }
}

<?php

namespace Tests\Feature\Ugc;

use Tests\TestCase;

/**
 * STRATO 1 — DA CANCELLARE (oc:8867): verifica il bind dei controller locali in AppServiceProvider, che
 * sparisce con loro all'aggiornamento di wm-package.
 */
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

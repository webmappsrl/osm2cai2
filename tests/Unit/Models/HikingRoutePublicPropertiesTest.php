<?php

namespace Tests\Unit\Models;

use App\Models\HikingRoute;
use Tests\TestCase;

class HikingRoutePublicPropertiesTest extends TestCase
{
    private function sicaiCompleto(): array
    {
        return [
            'referente' => ['name' => 'Mario Rossi', 'email' => 'mario@example.com'],
            'referente_regionale' => 'Luigi Verdi',
            'email_ref_regionale' => 'luigi@example.com',
            'sezione_ref_regionale' => 'Sez. Test',
            'sezioni_manutenzione' => 'Sez. A, Sez. B',
            'sezione' => 'Sez. Tappa',
            'percorribilità' => 'Percorribile',
            'segnaletica' => 'Buona',
        ];
    }

    public function test_rimuove_i_campi_sensibili_e_conserva_gli_altri(): void
    {
        $route = new HikingRoute;
        $route->properties = ['sicai' => $this->sicaiCompleto(), 'altro' => 'x'];

        $out = $route->getPublicProperties();

        $this->assertArrayNotHasKey('referente', $out['sicai']);
        foreach (['referente_regionale', 'email_ref_regionale', 'sezione_ref_regionale', 'sezioni_manutenzione'] as $key) {
            $this->assertArrayNotHasKey($key, $out['sicai']);
        }
        $this->assertSame('Sez. Tappa', $out['sicai']['sezione']);
        $this->assertSame('Percorribile', $out['sicai']['percorribilità']);
        $this->assertSame('Buona', $out['sicai']['segnaletica']);
        $this->assertSame('x', $out['altro']);
    }

    public function test_conserva_le_chiavi_extra_di_referente(): void
    {
        $route = new HikingRoute;
        $route->properties = ['sicai' => ['referente' => ['name' => 'M', 'email' => 'e', 'ruolo' => 'R']]];

        $this->assertSame(['ruolo' => 'R'], $route->getPublicProperties()['sicai']['referente']);
    }

    public function test_properties_senza_sicai_restano_identiche(): void
    {
        $route = new HikingRoute;
        $route->properties = ['altro' => 'x'];

        $this->assertSame(['altro' => 'x'], $route->getPublicProperties());
    }

    public function test_properties_nulle_restituiscono_array_vuoto(): void
    {
        $route = new HikingRoute;
        $route->properties = null;

        $this->assertSame([], $route->getPublicProperties());
    }

    public function test_non_modifica_le_properties_del_modello(): void
    {
        $route = new HikingRoute;
        $route->properties = ['sicai' => $this->sicaiCompleto()];

        $route->getPublicProperties();

        $this->assertSame('mario@example.com', $route->properties['sicai']['referente']['email']);
    }
}

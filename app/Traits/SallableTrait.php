<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait SallableTrait
{

    public function getSal()
    {
        $hikingRoutes = $this->intersectings['hiking_routes'] ?? [];

        $tot1 = 0;
        $tot2 = 0;
        $tot3 = 0;
        $tot4 = 0;

        if (is_array($hikingRoutes)) {
            foreach ($hikingRoutes as $route) {
                switch ($route['osm2cai_status'] ?? 0) {
                    case 1:
                        $tot1++;
                        break;
                    case 2:
                        $tot2++;
                        break;
                    case 3:
                        $tot3++;
                        break;
                    case 4:
                        $tot4++;
                        break;
                }
            }
        }

        if ($this->num_expected > 0) {
            $sal = ($tot1 * 0.25 + $tot2 * 0.50 + $tot3 * 0.75 + $tot4) / $this->num_expected;
            return min($sal, 1);
        }

        return 0;
    }
}

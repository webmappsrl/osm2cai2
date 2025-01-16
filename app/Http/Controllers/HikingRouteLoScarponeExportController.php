<?php

namespace App\Http\Controllers;

use App\Exports\HikingRouteLoScarponeExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class HikingRouteLoScarponeExportController extends Controller
{
    public function export()
    {
        return Excel::download(new HikingRouteLoScarponeExport, 'OSM2CAI_loscarpone.xlsx');
    }
}

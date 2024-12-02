<table class="table-auto w-full mt-5">
    <thead>
        <tr>
            <th class="px-4 py-2 text-center">Regioni</th>
            <th class="px-4 py-2 text-center">Gruppi Montuosi</th>
            <th class="px-4 py-2 text-center">POI Generico</th>
            <th class="px-4 py-2 text-center">POI Rifugio</th>
            <th class="px-4 py-2 text-center">Percorsi</th>
            <th class="px-4 py-2 text-center">POI Totali</th>
            <th class="px-4 py-2 text-center">Attivitá o Esperienze</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($regions as $region)
            @php
                $regionIntersections = $region->intersectings;
                $regionMountainGroupsCount = count($regionIntersections['mountain_groups'] ?? []);
                $regionEcPoisCount = count($regionIntersections['ec_pois'] ?? []);
                $regionCaiHutsCount = count($regionIntersections['cai_huts'] ?? []);
                $regionHikingRoutesCount = count($regionIntersections['hiking_routes'] ?? []);
                $regionClubsCount = count($regionIntersections['clubs'] ?? []);
                $regionPoiTotal = $regionEcPoisCount + $regionHikingRoutesCount;
            @endphp
            <tr class="border-b">
                <td class="px-4 py-2 text-center">{{ $region->name }}</td>
                <td class="px-4 py-2 text-center">{{ $regionMountainGroupsCount }}</td>
                <td class="px-4 py-2 text-center">{{ $regionEcPoisCount }}</td>
                <td class="px-4 py-2 text-center">{{ $regionCaiHutsCount }}</td>
                <td class="px-4 py-2 text-center">{{ $regionHikingRoutesCount }}</td>
                <td class="px-4 py-2 text-center">{{ $regionPoiTotal }}</td>
                <td class="px-4 py-2 text-center">{{ $regionClubsCount }}</td>
            </tr>
        @endforeach
        <tr class="border-t">
            <td class="px-4 py-2 font-bold text-center">Total</td>
            <td class="px-4 py-2 text-center">{{ $totals['sumMountainGroups'] }}</td>
            <td class="px-4 py-2 text-center">{{ $totals['sumEcPois'] }}</td>
            <td class="px-4 py-2 text-center">{{ $totals['sumCaiHuts'] }}</td>
            <td class="px-4 py-2 text-center">{{ $totals['sumHikingRoutes'] }}</td>
            <td class="px-4 py-2 text-center">{{ $totals['sumPoiTotal'] }}</td>
            <td class="px-4 py-2 text-center">{{ $totals['sumClubs'] }}</td>
        </tr>
    </tbody>
</table>

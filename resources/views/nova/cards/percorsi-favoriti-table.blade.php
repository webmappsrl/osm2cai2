<table class="table-auto w-full mt-5">
    <thead>
        <tr>
            <th class="px-4 py-2">Regioni</th>
            <th class="px-4 py-2 text-center">Percorsi Favoriti</th>
            <th class="px-4 py-2 text-center">Percorsi SDA4</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($regions as $region)
            <tr class="border-b">
                <td class="px-4 py-2">{{ $region->region_name }}</td>
                <td class="px-4 py-2 text-center">{{ $region->favorite_routes_count }}</td>
                <td class="px-4 py-2 text-center">{{ $region->sda4_routes_count }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

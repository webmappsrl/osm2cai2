<table class="table-auto w-full text-center">
    <thead>
        <tr>
            <th class="px-4 py-2">{{ __('Settore') }}</th>
            <th class="px-4 py-2">{{ __('Nome') }}</th>
            <th class="px-4 py-2">{{ __('#1') }}</th>
            <th class="px-4 py-2">{{ __('#2') }}</th>
            <th class="px-4 py-2">{{ __('#3') }}</th>
            <th class="px-4 py-2">{{ __('#4') }}</th>
            <th class="px-4 py-2">{{ __('#tot') }}</th>
            <th class="px-4 py-2">{{ __('#att') }}</th>
            <th class="px-4 py-2">{{ __('SAL') }}</th>
            <th class="px-4 py-2">{{ __('Actions') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($sectors as $sector)
            <tr class="border-b">
                <td class="px-4 py-2 text-center">{{ $sector->full_code }}</td>
                <td class="px-4 py-2 text-center">{{ $sector->human_name }}</td>
                <td class="px-4 py-2 text-center">{{ $sector->tot1 }}</td>
                <td class="px-4 py-2 text-center">{{ $sector->tot2 }}</td>
                <td class="px-4 py-2 text-center">{{ $sector->tot3 }}</td>
                <td class="px-4 py-2 text-center">{{ $sector->tot4 }}</td>
                <td class="px-4 py-2 text-center">{{ $sector->tot1 + $sector->tot2 + $sector->tot3 + $sector->tot4 }}
                </td>
                <td class="px-4 py-2 text-center">{{ $sector->num_expected }}</td>
                <td class="px-4 py-2 text-center">
                    <div style="background-color: {{ $sector->sal_color }}; color: white; font-size: x-large">
                        {{ number_format($sector->sal * 100, 2) }}%
                    </div>
                </td>
                <td class="px-4 py-2 text-center">
                    <a href="/resources/sectors/{{ $sector->id }}">[VIEW]</a>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

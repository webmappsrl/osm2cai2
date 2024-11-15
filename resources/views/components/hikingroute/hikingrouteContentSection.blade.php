@props(['hikingroute'])

@php
    use Carbon\Carbon;
    $issuesLastUpdate = Carbon::parse($hikingroute->issues_last_update);
    $issuesLastUpdate = $issuesLastUpdate->format('d/m/Y');
@endphp

<div class="content">
    @if ($hikingroute->osm2cai_status)
        <div id="tracksda" class="">
            <p class="">{{ __('Stato di accatastamento: ') }}<strong>{!! $hikingroute->osm2cai_status !!}</strong></p>
        </div>
    @endif
    @if ($hikingroute->issues_status)
        <div id="tracksda">
            <p class="">{{ __('Stato di percorribilitá: ') }}<strong>{!! $hikingroute->issues_status !!}</strong></p>
        </div>
    @endif
    @if ($hikingroute->relation_id)
        <div id="tracksda" class="">
            <p class="">{{ __('OSM ID: ') }}<strong>{!! $hikingroute->relation_id !!}</strong></p>
        </div>
    @endif
    @if ($hikingroute->cai_scale)
        <div id="tracksda" class="">
            <p class="">{{ __('Difficoltà: ') }}<strong>{!! $hikingroute->cai_scale !!}</strong></p>
        </div>
    @endif
    @if ($hikingroute->from)
        <div id="tracksda" class="">
            <p class="">{{ __('Località di partenza: ') }}<strong>{!! $hikingroute->from !!}</strong></p>
        </div>
    @endif
    @if ($hikingroute->to)
        <div id="tracksda" class="">
            <p class="">{{ __('Località di arrivo: ') }}<strong>{!! $hikingroute->to !!}</strong></p>
        </div>
    @endif
    @if ($hikingroute->rwn_name)
        <div id="tracksda" class="">
            <p class="">{{ __('Nome rete escursionistica: ') }}<strong>{!! $hikingroute->rwn_name !!}</strong></p>
        </div>
    @endif
    @if ($hikingroute->relation_id)
        <div id="tracksda" class="">
            <a target="_blank" href="https://www.openstreetmap.org/relation/{!! $hikingroute->relation_id !!}">
                <p><strong>{{ __('Open Street Map') }}</strong></p>
            </a>
        </div>
        <div id="tracksda" class="">
            <a target="_blank" href=" https://hiking.waymarkedtrails.org/#route?id={!! $hikingroute->relation_id !!}">
                <p><strong>{{ __('Waymarked Trails') }}</strong></p>
            </a>
        </div>
    @endif
    @if ($hikingroute->issues_last_update)
        <div id="tracksda" class="">
            <p class="">
                {{ __('Data ultimo rilevamento: ') }}<strong>{{ $issuesLastUpdate }}</strong>
            </p>
        </div>
    @endif
    @if (count($hikingroute->provinces) > 0)
        <div id="tracksda" class="">
            <p class="">{{ __('Comune/i di appartenenza: ') }}
                @foreach ($hikingroute->provinces as $province)
                    <strong>{!! $province->name !!} </strong>
                @endforeach
            </p>
        </div>
    @endif
    @if (count($hikingroute->regions) > 0)
        <div id="tracksda" class="">
            <p class="">{{ __('Regione: ') }}
                @foreach ($hikingroute->regions as $region)
                    <strong>{!! $region->name !!} </strong>
                @endforeach
            </p>
        </div>
    @endif
    @if ($hikingroute->ele_from)
        <div id="tracksda" class="">
            <p class="">{{ __('Altitudine di partenza: ') }}<strong>{!! $hikingroute->ele_from !!} m</strong></p>
        </div>
    @endif
    @if ($hikingroute->ele_to)
        <div id="tracksda" class="">
            <p class="">{{ __('Altitudine di arrivo: ') }}<strong>{!! $hikingroute->ele_to !!} m</strong></p>
        </div>
    @endif
    @if ($hikingroute->ele_max)
        <div id="tracksda" class="">
            <p class="">{{ __('Altitudine massima: ') }}<strong>{!! $hikingroute->ele_max !!} m</strong></p>
        </div>
    @endif
    @if ($hikingroute->ele_min)
        <div id="tracksda" class="">
            <p class="">{{ __('Altitudine minima: ') }}<strong>{!! $hikingroute->ele_min !!} m</strong></p>
        </div>
    @endif
    @if ($hikingroute->duration_forward)
        <div id="tracksda" class="">
            <p class="">{{ __('Tempo di percorrenza: ') }}<strong>{!! $hikingroute->duration_forward !!}</strong></p>
        </div>
    @endif
    @if ($hikingroute->duration_backward)
        <div id="tracksda" class="">
            <p class="">{{ __('Tempo di percorrenza inverso: ') }}<strong>{!! $hikingroute->duration_backward !!}</strong></p>
        </div>
    @endif
    @if ($hikingroute->distance)
        <div id="tracksda" class="">
            <p class="">{{ __('Lunghezza: ') }}<strong>{!! $hikingroute->distance !!} km</strong></p>
        </div>
    @endif
</div>

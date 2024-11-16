@props(['hikingroute'])


<header>
    <div class="" style="">
        @if ($hikingroute->ref)
            <div class="">
                <h1>REF: {!! $hikingroute->ref !!}</h1>
            </div>
        @endif
        @if ($hikingroute->ref_REI)
            <div id="trackref_REI" class="">
                <h3 class="">{{__('ref REI: ')}}{!! $hikingroute->ref_REI !!}</h3>
            </div>
        @endif
    </div>
</header>
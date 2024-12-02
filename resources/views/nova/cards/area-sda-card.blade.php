<div class="area-sda-card">
    <h3 style="background-color: {{ $backgroundColor }}; color: white; font-size: xx-large">
        {{ $num }}
    </h3>
    <div>
        @if ($num > 0)
            <a href="{{ $exploreUrl }}" target="_blank">#sda {{ $sda }}</a>
        @else
            #sda {{ $sda }}
        @endif
    </div>
</div>

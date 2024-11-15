<x-hikingroute.hikingrouteLayout :hikingroute="$hikingroute">
    <x-hikingroute.hikingrouteHeader :hikingroute="$hikingroute" />
    <main>
        <div class="content-wrapper">
            <x-hikingroute.hikingrouteContentSection :hikingroute="$hikingroute" />
            <x-mapsection :hikingroute="$hikingroute" />
        </div>
    </main>
</x-hikingroute.hikingrouteLayout>

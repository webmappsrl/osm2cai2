@php
    $og = OpenGraph::title($hikingroute->ref)
    ->siteName('Geohub | Webmapp')
@endphp
{!! $og->renderTags() !!}

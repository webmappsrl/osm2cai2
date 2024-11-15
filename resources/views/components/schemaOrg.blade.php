@props(['hikingroute'])
@php
    use Spatie\SchemaOrg\Schema;
    $CreativeWorkSeries = Schema::CreativeWorkSeries()
        ->headline($hikingroute->ref)
        ->name($hikingroute->ref)
        ->mainEntityOfPage(url()->current())
        ->publisher(Schema::Organization()
            ->name('Webmapp')
            ->url('https://webmapp.it')
                ->logo(Schema::ImageObject()
                    ->url('https://webmapp.it/wp-content/uploads/2016/07/webamapp-logo-1.png')
                )
        )
        ->dateCreated($hikingroute->created_at)
        ->datePublished($hikingroute->updated_at)
        ;

    echo $CreativeWorkSeries;
@endphp
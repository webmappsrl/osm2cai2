<?php

namespace App\Nova;

use App\Nova\Actions\GenerateTrailSurveyPdfAction;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\URL;
use Wm\WmPackage\Nova\Fields\FeatureCollectionGrid\FeatureCollectionGrid;
use Wm\WmPackage\Services\StorageService;

class TrailSurvey extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\TrailSurvey>
     */
    public static $model = \App\Models\TrailSurvey::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'description',
    ];

    /**
     * Get the resource label
     */
    public static function label()
    {
        return __('Trail Surveys');
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @return array
     */
    public function fields(Request $request)
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make(__('Hiking Route'), 'hikingRoute', HikingRoute::class)
                ->searchable()
                ->required()
                ->readonly(),

            BelongsTo::make(__('Owner'), 'owner', User::class)
                ->searchable()
                ->readonly()
                ->required(),

            Date::make(__('Start Date'), 'start_date')
                ->required()
                ->readonly()
                ->sortable(),

            Date::make(__('End Date'), 'end_date')
                ->required()
                ->readonly()
                ->sortable(),

            Textarea::make(__('Description IT'), 'description')
                ->nullable()
                ->rows(3),

            URL::make(__('PDF URL'), 'pdf_url')
                ->nullable()
                ->hideWhenUpdating()
                ->displayUsing(function ($value) {
                    // Get the path of the PDF file
                    $path = $this->resource->getPdfPath();

                    // Check if the file exists and generate the public URL using StorageService
                    $storageService = app(StorageService::class);
                    $publicDisk = $storageService->getPublicDisk();
                    $exists = $publicDisk->exists($path);

                    if ($exists) {
                        // Build the URL manually using the Laravel url() helper
                        // The path is relative to the root of the public disk, so remove the initial slash if present
                        $cleanPath = ltrim($path, '/');
                        $link = url('/storage/' . $cleanPath);
                        return '<a href="' . $link . '" target="_blank">Visualizza PDF</a>';
                    }

                    return 'Non disponibile';
                })
                ->asHtml(),

            BelongsToMany::make(__('Ugc Pois'), 'ugcPois', UgcPoi::class)
                ->searchable(),

            BelongsToMany::make(__('Ugc Tracks'), 'ugcTracks', UgcTrack::class)
                ->searchable(),

            FeatureCollectionGrid::make(__('UGC Features'), null)
                ->geojsonSource('getFeatureCollectionForGrid')
                ->syncRelations(['ugcPois', 'ugcTracks']),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @return array
     */
    public function cards(Request $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @return array
     */
    public function filters(Request $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array
     */
    public function lenses(Request $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array
     */
    public function actions(Request $request)
    {
        return [
            new GenerateTrailSurveyPdfAction(),
        ];
    }

    /**
     * Determine if the current user can create new models.
     */
    public static function authorizedToCreate(Request $request)
    {
        return false;
    }

    /**
     * Determine if the current user can update the given resource.
     */
    public function authorizedToUpdate(Request $request)
    {
        return $request->user()->can('update', $this->resource);
    }

    /**
     * Determine if the current user can delete the given resource.
     */
    public function authorizedToDelete(Request $request)
    {
        return $request->user()->can('delete', $this->resource);
    }

    /**
     * Determine if the current user can attach any models to the given resource.
     */
    public function authorizedToAttachAny(NovaRequest $request, $model)
    {
        return false;
    }
}

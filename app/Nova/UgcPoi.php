<?php

namespace App\Nova;

use App\Traits\Nova\UgcCommonFieldsTrait;
use App\Traits\Nova\UgcCommonMethodsTrait;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Heading;
use Wm\MapPoint\MapPoint;
use Wm\WmPackage\Nova\UgcPoi as WmUgcPoi;

class UgcPoi extends WmUgcPoi
{
    use UgcCommonFieldsTrait, UgcCommonMethodsTrait;

    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\UgcPoi::class;

    /**
     * Get the resource label
     */
    public static function label()
    {
        return static::getResourceLabel('Poi');
    }

    /**
     * Get the fields displayed by the resource.
     */
    public function fields(Request $request): array
    {
        $commonFields = $this->getCommonFields();

        // Helper per la creazione e edit (fino a quando non è stato selezionato un form)
        $title = __('To create a POI, follow these steps:');
        $step1 = __('Insert App and coordinates, optionally add one or more images.');
        $step2 = __('Once created, select one of the available forms.');
        $step3 = __('Once the form is selected, fill in the fields present in the form. The name is mandatory.');

        $helperText = <<<HTML
<div style="background-color: #e3f2fd; border-left: 4px solid #2196f3; padding: 16px; margin-bottom: 16px; border-radius: 4px;">
    <p style="margin: 0 0 12px 0; font-weight: 600; color: #1976d2;">{$title}</p>
    <ol style="margin: 0; padding-left: 20px; color: #424242;">
        <li style="margin-bottom: 8px;">{$step1}</li>
        <li style="margin-bottom: 8px;">{$step2}</li>
        <li style="margin-bottom: 8px;">{$step3}</li>
    </ol>
</div>
HTML;

        // Aggiungi helper all'inizio: mostra fino a quando non è stato inserito almeno il name nel form
        array_unshift($commonFields, Heading::make($helperText)
            ->asHtml()
            ->hideFromIndex()
            ->hideFromDetail()
            ->canSee(function ($request) {
                // Mostra sempre in creazione
                if ($request->isCreateOrAttachRequest()) {
                    return true;
                }
                // In edit, mostra solo se il name non è stato ancora inserito
                // Controlla sia properties->name che properties->form->title
                $name = $this->properties['name'] ?? null;
                $formTitle = isset($this->properties['form']['title']) ? $this->properties['form']['title'] : null;
                // Controlla se entrambi sono vuoti, null o contengono solo spazi
                $hasName = ! empty(trim($name ?? ''));
                $hasFormTitle = ! empty(trim($formTitle ?? ''));

                return ! ($hasName || $hasFormTitle);
            }));

        // Aggiungi MapPoint dopo tutti i campi comuni
        $commonFields[] = MapPoint::make('geometry')->withMeta([
            'center' => [43.7125, 10.4013],
            'attribution' => '<a href="https://webmapp.it/">Webmapp</a> contributors',
            'tiles' => 'https://api.webmapp.it/tiles/{z}/{x}/{y}.png',
            'minZoom' => 8,
            'maxZoom' => 14,
            'defaultZoom' => 10,
            'defaultCenter' => [43.7125, 10.4013],
        ])->hideFromIndex()
            ->required();

        return $commonFields;
    }

    /**
     * Get permission type for common actions
     */
    protected function getPermissionType(): string
    {
        return 'pois';
    }

    /**
     * Get the cards available for the request.
     */
    public function cards(Request $request): array
    {
        return $this->getCommonCards(static::$model);
    }
}

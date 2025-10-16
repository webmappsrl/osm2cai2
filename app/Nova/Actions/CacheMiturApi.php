<?php

namespace App\Nova\Actions;

use App\Jobs\CacheMiturAbruzzoDataJob;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class CacheMiturApi extends Action
{
    use InteractsWithQueue, Queueable;

    protected $class;

    public $name;

    public function __construct(string $class)
    {
        $this->class = $class;
        $this->name = __('Cache Mitur API');
    }

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        if (! app()->environment('production')) {
            return Action::danger(__('This action is only available in production'));
        }
        foreach ($models as $model) {
            CacheMiturAbruzzoDataJob::dispatch($this->class, $model->id);
        }
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [];
    }
}

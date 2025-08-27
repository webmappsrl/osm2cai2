<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\BooleanFilter;
use Laravel\Nova\Http\Requests\NovaRequest;

class OsmtagsFilter extends BooleanFilter
{
    protected string $tag;

    protected string $displayName;

    public function __construct(string $tag, string $displayName)
    {
        $this->tag = $tag;
        $this->displayName = $displayName;
    }

    public function name()
    {
        return $this->displayName;
    }

    public function key()
    {
        return static::class.':'.$this->tag;
    }

    public function apply(NovaRequest $request, $query, $value)
    {
        if (! is_array($value)) {
            return $query;
        }

        // Caso "Yes"
        if (! empty($value["has_{$this->tag}"])) {
            $sql = <<<'SQL'
                (
                    jsonb_exists(osmfeatures_data->'properties'->'osm_tags', ?)
                    AND osmfeatures_data->'properties'->'osm_tags'->>? IS NOT NULL
                )
            SQL;

            return $query->whereRaw($sql, [$this->tag, $this->tag]);
        }

        // Caso "No"
        if (! empty($value["no_{$this->tag}"])) {
            $sql = <<<'SQL'
                (
                    NOT jsonb_exists(osmfeatures_data->'properties'->'osm_tags', ?)
                    OR osmfeatures_data->'properties'->'osm_tags'->>? IS NULL
                )
            SQL;

            return $query->whereRaw($sql, [$this->tag, $this->tag]);
        }

        return $query;
    }

    public function options(NovaRequest $request)
    {
        return [
            'Yes' => "has_{$this->tag}",
            'No'  => "no_{$this->tag}",
        ];
    }
}

<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait GeneratesPdfTrait
{
    /**
     * Name of the Blade view to use for generating the PDF
     * Must be implemented by the model
     */
    public function getPdfViewName(): string
    {
        // Default: automatically generate from the model name
        $modelName = Str::snake(class_basename(static::class));
        return "{$modelName}.pdf";
    }

    /**
     * Name of the variable to pass to the view
     */
    public function getPdfViewVariableName(): string
    {
        return Str::snake(class_basename(static::class));
    }

    /**
     * Generate the path of the PDF file in storage
     * Must be implemented by the model
     */
    public function getPdfPath(): string
    {
        // Default: generic path
        $modelName = Str::plural(Str::snake(class_basename(static::class)));
        return "{$modelName}/{$this->id}/document_{$this->id}.pdf";
    }

    /**
     * Array of relations to load before generating the PDF
     */
    public function getPdfRelationsToLoad(): array
    {
        return [];
    }

    /**
     * Name of the database field where to save the PDF URL
     */
    public function getPdfUrlFieldName(): string
    {
        return 'pdf_url';
    }

    /**
     * Optional controller class for custom PDF generation logic
     * If null, uses standard generation
     */
    public function getPdfControllerClass(): ?string
    {
        return null;
    }

    /**
     * Sanitize a string to be used as a filename
     */
    protected function sanitizeFileName(string $name): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        $sanitized = preg_replace('/_+/', '_', $sanitized);
        return trim($sanitized, '_');
    }
}

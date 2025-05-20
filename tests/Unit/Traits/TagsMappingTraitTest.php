<?php

namespace Tests\Unit\Traits;

use App\Traits\TagsMappingTrait;
use Tests\TestCase;

class TagsMappingTraitTest extends TestCase
{
    private $testClass;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a class that uses the trait for testing
        $this->testClass = new class
        {
            use TagsMappingTrait;

            public $tags;

            public $osmfeatures_data;

            public function __construct()
            {
                // Simulate the configuration of the mapping
                config(['osm2cai.osmTagsMapping' => [
                    'highway' => [
                        'path' => 'Sentiero',
                        'track' => 'Mulattiera',
                    ],
                    'surface' => [
                        'gravel' => 'Ghiaia',
                        'paved' => 'Pavimentato',
                    ],
                ]]);
            }
        };
    }

    public function test_get_tags_mapping_from_tags()
    {
        $this->testClass->tags = json_encode([
            'highway' => 'path',
            'surface' => 'gravel',
        ]);

        $result = $this->testClass->getTagsMapping();

        $this->assertEquals('Sentiero;Ghiaia', $result);
    }

    public function test_get_tags_mapping_from_osmfeatures_data()
    {
        $this->testClass->tags = null;
        $this->testClass->osmfeatures_data = [
            'properties' => [
                'osm_tags' => [
                    'highway' => 'track',
                    'surface' => 'paved',
                ],
            ],
        ];

        $result = $this->testClass->getTagsMapping();

        $this->assertEquals('Mulattiera;Pavimentato', $result);
    }

    public function test_get_tags_mapping_with_string_osmfeatures_data()
    {
        $this->testClass->tags = null;
        $this->testClass->osmfeatures_data = json_encode([
            'properties' => [
                'osm_tags' => [
                    'highway' => 'track',
                    'surface' => 'paved',
                ],
            ],
        ]);

        $result = $this->testClass->getTagsMapping();

        $this->assertEquals('Mulattiera;Pavimentato', $result);
    }

    public function test_get_tags_mapping_with_unmapped_tags()
    {
        $this->testClass->tags = json_encode([
            'highway' => 'nonexistent',
            'surface' => 'unknown',
        ]);

        $result = $this->testClass->getTagsMapping();

        $this->assertEquals('', $result);
    }

    public function test_get_tags_mapping_with_mixed_tags()
    {
        $this->testClass->tags = json_encode([
            'highway' => 'path',
            'surface' => 'unknown',
            'nonexistent_key' => 'value',
        ]);

        $result = $this->testClass->getTagsMapping();

        $this->assertEquals('Sentiero', $result);
    }

    public function test_get_tags_mapping_with_null_tags()
    {
        $this->testClass->tags = null;
        $this->testClass->osmfeatures_data = null;

        $result = $this->testClass->getTagsMapping();

        $this->assertEquals('', $result);
    }

    public function test_get_tags_mapping_with_empty_tags()
    {
        $this->testClass->tags = json_encode([]);
        $this->testClass->osmfeatures_data = null;

        $result = $this->testClass->getTagsMapping();

        $this->assertEquals('', $result);
    }

    public function test_get_tags_mapping_with_null_osmfeatures_data()
    {
        $this->testClass->tags = json_encode([
            'highway' => 'path',
            'surface' => 'gravel',
        ]);
        $this->testClass->osmfeatures_data = null;

        $result = $this->testClass->getTagsMapping();

        $this->assertEquals('Sentiero;Ghiaia', $result);
    }

    public function test_get_tags_mapping_with_empty_osmfeatures_data()
    {
        $this->testClass->tags = null;
        $this->testClass->osmfeatures_data = [
            'properties' => [
                'osm_tags' => [],
            ],
        ];

        $result = $this->testClass->getTagsMapping();

        $this->assertEquals('', $result);
    }

    public function test_get_tags_mapping_with_invalid_json_tags()
    {
        $this->testClass->tags = 'invalid-json';
        $this->testClass->osmfeatures_data = null;

        $result = $this->testClass->getTagsMapping();

        $this->assertEquals('', $result);
    }

    public function test_get_tags_mapping_with_invalid_osmfeatures_data_structure()
    {
        $this->testClass->tags = null;
        $this->testClass->osmfeatures_data = [
            'invalid' => 'structure',
        ];

        $result = $this->testClass->getTagsMapping();

        $this->assertEquals('', $result);
    }
}

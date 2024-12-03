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
        $this->testClass = new class {
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

    public function testGetTagsMappingFromTags()
    {
        $this->testClass->tags = json_encode([
            'highway' => 'path',
            'surface' => 'gravel',
        ]);

        $result = $this->testClass->getTagsMapping();

        $this->assertEquals('Sentiero;Ghiaia', $result);
    }

    public function testGetTagsMappingFromOsmfeaturesData()
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

    public function testGetTagsMappingWithStringOsmfeaturesData()
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

    public function testGetTagsMappingWithUnmappedTags()
    {
        $this->testClass->tags = json_encode([
            'highway' => 'nonexistent',
            'surface' => 'unknown',
        ]);

        $result = $this->testClass->getTagsMapping();

        $this->assertEquals('', $result);
    }

    public function testGetTagsMappingWithMixedTags()
    {
        $this->testClass->tags = json_encode([
            'highway' => 'path',
            'surface' => 'unknown',
            'nonexistent_key' => 'value',
        ]);

        $result = $this->testClass->getTagsMapping();

        $this->assertEquals('Sentiero', $result);
    }

    public function testGetTagsMappingWithNullTags()
    {
        $this->testClass->tags = null;
        $this->testClass->osmfeatures_data = null;

        $result = $this->testClass->getTagsMapping();

        $this->assertEquals('', $result);
    }

    public function testGetTagsMappingWithEmptyTags()
    {
        $this->testClass->tags = json_encode([]);
        $this->testClass->osmfeatures_data = null;

        $result = $this->testClass->getTagsMapping();

        $this->assertEquals('', $result);
    }

    public function testGetTagsMappingWithNullOsmfeaturesData()
    {
        $this->testClass->tags = json_encode([
            'highway' => 'path',
            'surface' => 'gravel',
        ]);
        $this->testClass->osmfeatures_data = null;

        $result = $this->testClass->getTagsMapping();

        $this->assertEquals('Sentiero;Ghiaia', $result);
    }

    public function testGetTagsMappingWithEmptyOsmfeaturesData()
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

    public function testGetTagsMappingWithInvalidJsonTags()
    {
        $this->testClass->tags = 'invalid-json';
        $this->testClass->osmfeatures_data = null;

        $result = $this->testClass->getTagsMapping();

        $this->assertEquals('', $result);
    }

    public function testGetTagsMappingWithInvalidOsmfeaturesDataStructure()
    {
        $this->testClass->tags = null;
        $this->testClass->osmfeatures_data = [
            'invalid' => 'structure',
        ];

        $result = $this->testClass->getTagsMapping();

        $this->assertEquals('', $result);
    }
}

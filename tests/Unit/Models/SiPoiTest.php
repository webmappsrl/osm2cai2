<?php

namespace Tests\Unit\Models;

use App\Models\SiPoi;
use Illuminate\Contracts\Events\Dispatcher;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use ReflectionClass;
use Tests\TestCase;

class SiPoiTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** @var callable|null */
    private $creatingListener = null;

    protected function setUp(): void
    {
        parent::setUp();

        SiPoi::flushEventListeners();
        SiPoi::clearBootedModels();

        $dispatcher = Mockery::mock(Dispatcher::class);

        $dispatcher->shouldReceive('listen')
            ->andReturnUsing(function ($event, $listener): void {
                if ($event === 'eloquent.creating: ' . SiPoi::class && is_callable($listener)) {
                    $this->creatingListener = $listener;
                }
            });

        $dispatcher->shouldReceive('until')
            ->andReturnUsing(function (string $event, $payload) {
                if ($event !== 'eloquent.creating: ' . SiPoi::class || ! is_callable($this->creatingListener)) {
                    return null;
                }

                $model = is_array($payload) ? ($payload[0] ?? null) : $payload;
                if (! $model instanceof SiPoi) {
                    return null;
                }

                return call_user_func($this->creatingListener, $model);
            });

        $dispatcher->shouldReceive('dispatch')
            ->andReturnUsing(function (string $event, $payload) {
                if ($event !== 'eloquent.creating: ' . SiPoi::class || ! is_callable($this->creatingListener)) {
                    return null;
                }

                $model = is_array($payload) ? ($payload[0] ?? null) : $payload;
                if (! $model instanceof SiPoi) {
                    return null;
                }

                return call_user_func($this->creatingListener, $model);
            });

        SiPoi::setEventDispatcher($dispatcher);

        // Trigger del boot per registrare i listener sul dispatcher mockato
        new SiPoi();
    }

    /** @test */
    public function it_sets_default_app_id_and_dbtable_on_creating_when_missing()
    {
        $model = new SiPoi();
        $model->properties = null;

        $this->fireCreatingEvent($model);

        $this->assertSame(2, $model->app_id);
        $this->assertIsArray($model->properties);
        $this->assertSame('pt_accoglienza_unofficial', $model->properties['sicai']['dbtable']);
    }

    /** @test */
    public function it_does_not_override_existing_app_id_and_dbtable_on_creating()
    {
        $model = new SiPoi();
        $model->app_id = 7;
        $model->properties = [
            'sicai' => [
                'dbtable' => 'custom_source',
            ],
        ];

        $this->fireCreatingEvent($model);

        $this->assertSame(7, $model->app_id);
        $this->assertSame('custom_source', $model->properties['sicai']['dbtable']);
    }

    /** @test */
    public function it_initializes_sicai_structure_when_properties_are_malformed()
    {
        $model = new SiPoi();
        $model->properties = [
            'sicai' => 'invalid',
        ];

        $this->fireCreatingEvent($model);

        $this->assertSame('pt_accoglienza_unofficial', $model->properties['sicai']['dbtable']);
    }

    private function fireCreatingEvent(SiPoi $model): void
    {
        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('fireModelEvent');
        $method->setAccessible(true);
        $method->invoke($model, 'creating', true);
    }
}

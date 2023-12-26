<?php

namespace Lomkit\Access\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Lomkit\Access\Perimeters\Perimeters;
use Lomkit\Access\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Access\Tests\Support\Models\Model;

class QueriesTest extends TestCase
{
    public function test_should_default_perimeter(): void
    {
        Cache::set('model-should-client', true);
        Cache::set('model-should-site', true);
        Cache::set('model-should-own', true);

        $model = ModelFactory::new()->create(['is_client' => true]);
        ModelFactory::new()->create(['is_site' => true]);
        ModelFactory::new()->create(['is_own' => true]);
        ModelFactory::new()->create();

        $this->assertEquals(
            [$model->fresh()->toArray()],
            Model::query()->get()->toArray()
        );
    }

    public function test_should_second_perimeter(): void
    {
        Cache::set('model-should-client', false);
        Cache::set('model-should-site', true);
        Cache::set('model-should-own', true);

        ModelFactory::new()->create(['is_client' => true]);
        $model = ModelFactory::new()->create(['is_site' => true]);
        ModelFactory::new()->create(['is_own' => true]);

        $this->assertEquals(
            [$model->fresh()->toArray()],
            Model::query()->get()->toArray()
        );
    }

    public function test_should_third_perimeter(): void
    {
        Cache::set('model-should-client', false);
        Cache::set('model-should-site', false);
        Cache::set('model-should-own', true);

        ModelFactory::new()->create(['is_client' => true]);
        ModelFactory::new()->create(['is_site' => true]);
        $model = ModelFactory::new()->create(['is_own' => true]);

        $this->assertEquals(
            [$model->fresh()->toArray()],
            Model::query()->get()->toArray()
        );
    }
}
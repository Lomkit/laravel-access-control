<?php

namespace Lomkit\Access\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Lomkit\Access\Perimeters\Perimeters;
use Lomkit\Access\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Access\Tests\Support\Models\Model;

class ControlsTest extends TestCase
{
    public function test_should_default_perimeter(): void
    {
        Cache::set('model-should-client', true);
        Cache::set('model-should-site', true);
        Cache::set('model-should-own', true);

        $model = ModelFactory::new()->create(['is_client' => true]);
        ModelFactory::new()->create();

        // @TODO: factorize this in a trait
        $this->assertEquals(
            [$model->fresh()->toArray()],
            Model::query()->get()->toArray()
        );
    }
}
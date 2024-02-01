<?php

namespace Lomkit\Access\Tests\Feature;

use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Lomkit\Access\Exceptions\QueryNotImplemented;
use Lomkit\Access\Perimeters\Perimeters;
use Lomkit\Access\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Access\Tests\Support\Models\Model;
use Lomkit\Access\Tests\Support\Models\NotImplementedQueryModel;

class QueriesTest extends TestCase
{
    public function test_not_implemented_query(): void
    {
        Cache::set('model-should-client', true);

        ModelFactory::new()->create(['is_client' => true]);

        $this->assertThrows(
            fn () => NotImplementedQueryModel::query()->get(),
            QueryNotImplemented::class
        );
    }

    public function test_should_first_perimeter(): void
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

    public function test_without_control_scope(): void
    {
        Cache::set('model-should-client', true);
        Cache::set('model-should-site', true);
        Cache::set('model-should-own', true);

        $models =
            ModelFactory::new()
                ->count(3)
                ->create(
                    new Sequence(
                        ['is_client' => true],
                        ['is_site' => true],
                        ['is_own' => true],
                    )
                );

        $this->assertEquals(
            $models->fresh()->toArray(),
            Model::query()->withoutControl()->get()->toArray()
        );
    }

    public function test_unauthenticated(): void
    {
        Auth::logout();

        ModelFactory::new()
            ->count(3)
            ->create(
                new Sequence(
                    ['is_client' => true],
                    ['is_site' => true],
                    ['is_own' => true],
                )
            );

        $this->assertEquals(
            [],
            Model::query()->get()->toArray()
        );
    }

    public function test_default_query(): void
    {
        ModelFactory::new()
            ->count(3)
            ->create(
                new Sequence(
                    ['is_client' => true],
                    ['is_site' => true],
                    ['is_own' => true],
                )
            );

        $this->assertEquals(
            [],
            Model::query()->get()->toArray()
        );
    }
}
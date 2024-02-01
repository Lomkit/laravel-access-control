<?php

namespace Lomkit\Access\Tests\Unit;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Perimeters\Perimeters;
use Lomkit\Access\Tests\Support\Access\Policies\ModelPolicy;
use Lomkit\Access\Tests\Support\Models\Model;
use Mockery;

class PoliciesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_policy_view()
    {
        $controlMock = Mockery::mock(\Lomkit\Access\Tests\Support\Access\Controls\ModelControl::class, [app(Perimeters::class)])
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        Cache::set('model-should-own', true);

        Gate::policy(Model::class, ModelPolicy::class);

        $model = \Lomkit\Access\Tests\Support\Database\Factories\ModelFactory::new()->create();

        $controlMock->shouldReceive('clientPolicy')->never();
        $controlMock->shouldReceive('sitePolicy')->never();
        $controlMock->shouldReceive('ownPolicy')->with('view', \Illuminate\Support\Facades\Auth::user(), $model)->once()->andReturn(true);

        $this->assertTrue(
            $controlMock->runPolicy('view', Auth::user(), $model)
        );
    }

    public function test_policy_update()
    {
        $controlMock = Mockery::mock(\Lomkit\Access\Tests\Support\Access\Controls\ModelControl::class, [app(Perimeters::class)])
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        Cache::set('model-should-site', true);
        Cache::set('model-should-own', true);

        Gate::policy(Model::class, ModelPolicy::class);

        $model = \Lomkit\Access\Tests\Support\Database\Factories\ModelFactory::new()->create();

        $controlMock->shouldReceive('clientPolicy')->never();
        $controlMock->shouldReceive('sitePolicy')->with('update', \Illuminate\Support\Facades\Auth::user(), $model)->once()->andReturn(true);
        $controlMock->shouldReceive('ownPolicy')->never();

        $this->assertTrue(
            $controlMock->runPolicy('update', Auth::user(), $model)
        );
    }

    public function test_policy_delete()
    {
        $controlMock = Mockery::mock(\Lomkit\Access\Tests\Support\Access\Controls\ModelControl::class, [app(Perimeters::class)])
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        Cache::set('model-should-client', true);
        Cache::set('model-should-site', true);
        Cache::set('model-should-own', true);

        Gate::policy(Model::class, ModelPolicy::class);

        $model = \Lomkit\Access\Tests\Support\Database\Factories\ModelFactory::new()->create();

        $controlMock->shouldReceive('clientPolicy')->with('delete', \Illuminate\Support\Facades\Auth::user(), $model)->once()->andReturn(true);
        $controlMock->shouldReceive('sitePolicy')->never();
        $controlMock->shouldReceive('ownPolicy')->never();

        $this->assertTrue(
            $controlMock->runPolicy('delete', Auth::user(), $model)
        );
    }
}
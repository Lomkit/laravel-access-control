<?php

use Illuminate\Support\Facades\Cache;

class PoliciesTest extends \Lomkit\Access\Tests\TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_policy_view()
    {
        $controlMock = Mockery::mock(\Lomkit\Access\Tests\Support\Access\Controls\ModelControl::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        Cache::set('model-should-own', true);

        $model = \Lomkit\Access\Tests\Support\Database\Factories\ModelFactory::new()->create();

        $controlMock->shouldReceive('clientPolicy')->with(\Lomkit\Access\Tests\Support\Access\Perimeters\ClientPerimeter::class, 'viewAny', \Illuminate\Support\Facades\Auth::user(), $model)->once()->andReturn(false);
        $controlMock->shouldReceive('sitePolicy')->with(\Lomkit\Access\Tests\Support\Access\Perimeters\SitePerimeter::class, 'viewAny', \Illuminate\Support\Facades\Auth::user(), $model)->once()->andReturn(false);
        $controlMock->shouldReceive('ownPolicy')->with(\Lomkit\Access\Tests\Support\Access\Perimeters\OwnPerimeter::class, 'viewAny', \Illuminate\Support\Facades\Auth::user(), $model)->once()->andReturn(true);

//        $this->assertTrue(
//            $controlMock
//                ->should(new \Lomkit\Access\Tests\Support\Access\Perimeters\ClientPerimeter)
//        );
        \Illuminate\Support\Facades\Auth::user()->can('view', $model::class);
    }
}
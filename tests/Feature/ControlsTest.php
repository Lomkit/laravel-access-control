<?php

namespace Lomkit\Access\Tests\Feature;

use Lomkit\Access\Perimeters\Perimeters;
use Lomkit\Access\Tests\Support\Models\Model;

class ControlsTest extends TestCase
{
    public function test_should_default_perimeter(): void
    {
        Model::query()->control()->get();
        dd(app(Perimeters::class)->getPerimeters());

        $this->assertTrue(true);
    }
}
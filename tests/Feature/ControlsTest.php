<?php

namespace Lomkit\Access\Tests\Feature;

use Lomkit\Access\Perimeters\Perimeters;

class ControlsTest extends TestCase
{
    public function test_should_default_perimeter(): void
    {
        dd(app(Perimeters::class)->getPerimeters());

        $this->assertTrue(true);
    }
}
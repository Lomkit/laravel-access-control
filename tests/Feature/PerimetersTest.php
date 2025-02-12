<?php

namespace Lomkit\Access\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Lomkit\Access\Tests\Support\Access\Perimeters\ClientPerimeter;
use Lomkit\Access\Tests\Support\Models\Model;

class PerimetersTest extends TestCase
{
    public function test_should_client_perimeter(): void
    {
        Auth::user()->update(['should_client' => true]);

        $this->assertTrue((new ClientPerimeter())->should(Auth::user(), 'create', new Model()));
    }

    public function test_should_not_client_perimeter(): void
    {
        Auth::user()->update(['should_client' => false]);

        $this->assertFalse((new ClientPerimeter())->should(Auth::user(), 'create', new Model()));
    }

    // @TODO: other perimeters
}

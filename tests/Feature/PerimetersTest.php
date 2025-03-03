<?php

namespace Lomkit\Access\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Lomkit\Access\Tests\Support\Access\Perimeters\ClientPerimeter;
use Lomkit\Access\Tests\Support\Access\Perimeters\GlobalPerimeter;
use Lomkit\Access\Tests\Support\Access\Perimeters\OwnPerimeter;
use Lomkit\Access\Tests\Support\Access\Perimeters\SharedPerimeter;
use Lomkit\Access\Tests\Support\Models\Model;

class PerimetersTest extends TestCase
{
    public function test_should_client_perimeter(): void
    {
        Auth::user()->update(['should_client' => true]);

        $this->assertTrue((new ClientPerimeter())->applies(Auth::user()));
    }

    public function test_should_not_client_perimeter(): void
    {
        Auth::user()->update(['should_client' => false]);

        $this->assertFalse((new ClientPerimeter())->applies(Auth::user()));
    }

    public function test_should_global_perimeter(): void
    {
        Auth::user()->update(['should_global' => true]);

        $this->assertTrue((new GlobalPerimeter())->applies(Auth::user()));
    }

    public function test_should_not_global_perimeter(): void
    {
        Auth::user()->update(['should_global' => false]);

        $this->assertFalse((new GlobalPerimeter())->applies(Auth::user()));
    }

    public function test_should_own_perimeter(): void
    {
        Auth::user()->update(['should_own' => true]);

        $this->assertTrue((new OwnPerimeter())->applies(Auth::user()));
    }

    public function test_should_not_own_perimeter(): void
    {
        Auth::user()->update(['should_own' => false]);

        $this->assertFalse((new OwnPerimeter())->applies(Auth::user()));
    }

    public function test_should_shared_perimeter(): void
    {
        Auth::user()->update(['should_shared' => true]);

        $this->assertTrue((new SharedPerimeter())->applies(Auth::user()));
    }

    public function test_should_not_shared_perimeter(): void
    {
        Auth::user()->update(['should_shared' => false]);

        $this->assertFalse((new SharedPerimeter())->applies(Auth::user()));
    }
}

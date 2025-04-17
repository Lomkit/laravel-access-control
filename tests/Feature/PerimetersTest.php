<?php

namespace Lomkit\Access\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Lomkit\Access\Tests\Support\Access\Perimeters\ClientPerimeter;
use Lomkit\Access\Tests\Support\Access\Perimeters\SharedPerimeter;

class PerimetersTest extends TestCase
{
    public function test_should_client_perimeter(): void
    {
        $this->assertTrue((new ClientPerimeter())->allowed(function (Model $user, string $method) { return true; })->applyAllowedCallback(Auth::user(), ''));
    }

    public function test_should_not_shared_perimeter(): void
    {
        $this->assertFalse((new SharedPerimeter())->allowed(function (Model $user, string $method) { return false; })->applyAllowedCallback(Auth::user(), ''));
    }
}

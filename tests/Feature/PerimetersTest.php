<?php

namespace Lomkit\Access\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Lomkit\Access\Tests\Support\Access\Perimeters\ClientPerimeter;
use Lomkit\Access\Tests\Support\Access\Perimeters\GlobalPerimeter;
use Lomkit\Access\Tests\Support\Access\Perimeters\OwnPerimeter;
use Lomkit\Access\Tests\Support\Access\Perimeters\SharedPerimeter;

class PerimetersTest extends TestCase
{
    public function test_should_client_perimeter(): void
    {
        Auth::user()->update(['should_client' => true]);

        $this->assertTrue((new ClientPerimeter())->allowed(function(Model $user) { return $user->should_client; })->applyAllowedCallback(Auth::user()));
    }

    public function test_should_not_client_perimeter(): void
    {
        Auth::user()->update(['should_client' => false]);

        $this->assertFalse((new ClientPerimeter())->allowed(function(Model $user) { return $user->should_client; })->applyAllowedCallback(Auth::user()));
    }

    public function test_should_global_perimeter(): void
    {
        Auth::user()->update(['should_global' => true]);

        $this->assertTrue((new GlobalPerimeter())->allowed(function(Model $user) { return $user->should_global; })->applyAllowedCallback(Auth::user()));
    }

    public function test_should_not_global_perimeter(): void
    {
        Auth::user()->update(['should_global' => false]);

        $this->assertFalse((new GlobalPerimeter())->allowed(function(Model $user) { return $user->should_global; })->applyAllowedCallback(Auth::user()));
    }

    public function test_should_own_perimeter(): void
    {
        Auth::user()->update(['should_own' => true]);

        $this->assertTrue((new OwnPerimeter())->allowed(function(Model $user) { return $user->should_own; })->applyAllowedCallback(Auth::user()));
    }

    public function test_should_not_own_perimeter(): void
    {
        Auth::user()->update(['should_own' => false]);

        $this->assertFalse((new OwnPerimeter())->allowed(function(Model $user) { return $user->should_own; })->applyAllowedCallback(Auth::user()));
    }

    public function test_should_shared_perimeter(): void
    {
        Auth::user()->update(['should_shared' => true]);

        $this->assertTrue((new SharedPerimeter())->allowed(function(Model $user) { return $user->should_shared; })->applyAllowedCallback(Auth::user()));
    }

    public function test_should_not_shared_perimeter(): void
    {
        Auth::user()->update(['should_shared' => false]);

        $this->assertFalse((new SharedPerimeter())->allowed(function(Model $user) { return $user->should_shared; })->applyAllowedCallback(Auth::user()));
    }
}

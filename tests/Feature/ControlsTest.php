<?php


use Illuminate\Support\Facades\Auth;
use Lomkit\Access\Tests\Support\Access\Perimeters\ClientPerimeter;
use Lomkit\Access\Tests\Support\Access\Perimeters\GlobalPerimeter;
use Lomkit\Access\Tests\Support\Access\Perimeters\OwnPerimeter;
use Lomkit\Access\Tests\Support\Access\Perimeters\SharedPerimeter;
use Lomkit\Access\Tests\Support\Models\Model;

class ControlsTest extends \Lomkit\Access\Tests\Feature\TestCase
{
    public function test_control_with_no_perimeter_passing(): void
    {
        $this->assertFalse((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'create', new Model));
    }

    public function test_control_should_view_any_using_client_perimeter(): void
    {
        Auth::user()->update(['should_client' => true]);

        $this->assertTrue((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'viewAny', new Model));
    }

    public function test_control_should_view_using_client_perimeter(): void
    {
        Auth::user()->update(['should_client' => true]);
        $model = Model::factory()
            ->create([
                'allowed_methods' => 'view',
            ]);

        $this->assertTrue((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'view', $model));
    }

    public function test_control_should_not_view_using_client_perimeter(): void
    {
        Auth::user()->update(['should_client' => true]);
        $model = Model::factory()
            ->create([
                'allowed_methods' => 'create',
            ]);

        $this->assertFalse((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'view', $model));
    }

    public function test_control_should_create_using_client_perimeter(): void
    {
        Auth::user()->update(['should_client' => true]);

        $this->assertTrue((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'create', new Model));
    }

    public function test_control_should_update_using_client_perimeter(): void
    {
        Auth::user()->update(['should_client' => true]);
        $model = Model::factory()
            ->create([
                'allowed_methods' => 'update',
            ]);

        $this->assertTrue((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'update', $model));
    }

    public function test_control_should_delete_using_client_perimeter(): void
    {
        Auth::user()->update(['should_client' => true]);
        $model = Model::factory()
            ->create([
                'allowed_methods' => 'delete',
            ]);

        $this->assertTrue((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'delete', $model));
    }

    public function test_control_should_view_any_using_global_perimeter(): void
    {
        Auth::user()->update(['should_global' => true]);

        $this->assertTrue((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'viewAny', new Model));
    }

    public function test_control_should_view_using_global_perimeter(): void
    {
        Auth::user()->update(['should_global' => true]);
        $model = Model::factory()
            ->create([
                'allowed_methods' => 'view',
            ]);

        $this->assertTrue((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'view', $model));
    }

    public function test_control_should_not_view_using_global_perimeter(): void
    {
        Auth::user()->update(['should_global' => true]);
        $model = Model::factory()
            ->create([
                'allowed_methods' => 'create',
            ]);

        $this->assertFalse((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'view', $model));
    }

    public function test_control_should_create_using_global_perimeter(): void
    {
        Auth::user()->update(['should_global' => true]);

        $this->assertTrue((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'create', new Model));
    }

    public function test_control_should_update_using_global_perimeter(): void
    {
        Auth::user()->update(['should_global' => true]);
        $model = Model::factory()
            ->create([
                'allowed_methods' => 'update',
            ]);

        $this->assertTrue((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'update', $model));
    }

    public function test_control_should_delete_using_global_perimeter(): void
    {
        Auth::user()->update(['should_global' => true]);
        $model = Model::factory()
            ->create([
                'allowed_methods' => 'delete',
            ]);

        $this->assertTrue((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'delete', $model));
    }
}

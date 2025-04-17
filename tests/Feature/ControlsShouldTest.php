<?php

namespace Lomkit\Access\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Tests\Support\Models\Model;
use Lomkit\Access\Tests\Support\Models\User;

class ControlsShouldTest extends \Lomkit\Access\Tests\Feature\TestCase
{
    public function test_control_with_no_perimeter_passing(): void
    {
        $this->assertFalse((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'create', new Model()));
    }

    public function test_control_should_view_any_using_client_perimeter(): void
    {
        Gate::define('viewAny client models', function (User $user) {
            return true;
        });

        $this->assertTrue((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'viewAny', new Model()));
    }

    public function test_control_should_view_using_client_perimeter(): void
    {
        Gate::define('view client models', function (User $user) {
            return true;
        });

        $model = Model::factory()
            ->clientPerimeter()
            ->create();

        $this->assertTrue((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'view', $model));
    }

    public function test_control_should_not_view_using_client_perimeter(): void
    {
        Gate::define('update client models', function (User $user) {
            return true;
        });

        $model = Model::factory()
            ->create();

        $this->assertFalse((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'view', $model));
    }

    public function test_control_should_create_using_client_perimeter(): void
    {
        Gate::define('create global models', function (User $user) {
            return true;
        });

        $this->assertTrue((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'create', new Model()));
    }

    public function test_control_should_update_using_client_perimeter(): void
    {
        Gate::define('update client models', function (User $user) {
            return true;
        });

        $model = Model::factory()
            ->clientPerimeter()
            ->create();

        $this->assertTrue((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'update', $model));
    }

    public function test_control_should_delete_using_client_perimeter(): void
    {
        Gate::define('delete client models', function (User $user) {
            return true;
        });

        $model = Model::factory()
            ->clientPerimeter()
            ->create();

        $this->assertTrue((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'delete', $model));
    }

    public function test_control_should_view_any_using_global_perimeter(): void
    {
        Gate::define('viewAny global models', function (User $user) {
            return true;
        });

        $this->assertTrue((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'viewAny', new Model()));
    }

    public function test_control_should_view_using_global_perimeter(): void
    {
        Gate::define('view global models', function (User $user) {
            return true;
        });

        $model = Model::factory()
            ->create();

        $this->assertTrue((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'view', $model));
    }

    public function test_control_should_not_view_using_global_perimeter(): void
    {
        Gate::define('view client models', function (User $user) {
            return true;
        });

        $model = Model::factory()
            ->create();

        $this->assertFalse((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'view', $model));
    }

    public function test_control_should_create_using_global_perimeter(): void
    {
        Gate::define('create global models', function (User $user) {
            return true;
        });

        $this->assertTrue((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'create', new Model()));
    }

    public function test_control_should_update_using_global_perimeter(): void
    {
        Gate::define('update global models', function (User $user) {
            return true;
        });

        $model = Model::factory()
            ->create();

        $this->assertTrue((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'update', $model));
    }

    public function test_control_should_delete_using_global_perimeter(): void
    {
        Gate::define('delete global models', function (User $user) {
            return true;
        });

        $model = Model::factory()
            ->create();

        $this->assertTrue((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'delete', $model));
    }

    public function test_control_should_delete_global_using_shared_overlayed_perimeter(): void
    {
        Gate::define('delete shared models', function (User $user) {
            return true;
        });
        Gate::define('delete global models', function (User $user) {
            return true;
        });
        $model = Model::factory()
            ->create();

        $this->assertTrue((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'delete', $model));
    }

    public function test_control_should_delete_using_shared_overlayed_perimeter(): void
    {
        Gate::define('delete shared models', function (User $user) {
            return true;
        });
        Gate::define('delete global models', function (User $user) {
            return true;
        });

        $model = Model::factory()
            ->create();

        $this->assertTrue((new \Lomkit\Access\Tests\Support\Access\Controls\ModelControl())->applies(Auth::user(), 'delete', $model));
    }
}

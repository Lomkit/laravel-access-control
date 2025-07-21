<?php

namespace Lomkit\Access\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Tests\Support\Models\Model;
use Lomkit\Access\Tests\Support\Models\User;

class PoliciesTest extends \Lomkit\Access\Tests\Unit\TestCase
{
    public function test_policies_calls_view_method_properly(): void
    {
        Gate::define('view global models', function (User $user) {
            return true;
        });
        $model = Model::factory()
            ->createOne();
        $user = \Illuminate\Support\Facades\Auth::user();

        $policy = new \Lomkit\Access\Tests\Support\Policies\ModelPolicy();

        $this->assertTrue($policy->view($user, $model));
    }

    public function test_policies_calls_view_any_method_properly(): void
    {
        Gate::define('view global models', function (User $user) {
            return true;
        });
        $user = \Illuminate\Support\Facades\Auth::user();

        $policy = new \Lomkit\Access\Tests\Support\Policies\ModelPolicy();

        $this->assertTrue($policy->viewAny($user));
    }

    public function test_policies_calls_create_method_properly(): void
    {
        Gate::define('create global models', function (User $user) {
            return true;
        });
        $user = \Illuminate\Support\Facades\Auth::user();

        $policy = new \Lomkit\Access\Tests\Support\Policies\ModelPolicy();

        $this->assertTrue($policy->create($user));
    }

    public function test_policies_calls_update_method_properly(): void
    {
        Gate::define('update global models', function (User $user) {
            return true;
        });
        $model = Model::factory()
            ->createOne();
        $user = \Illuminate\Support\Facades\Auth::user();

        $policy = new \Lomkit\Access\Tests\Support\Policies\ModelPolicy();

        $this->assertTrue($policy->update($user, $model));
    }

    public function test_policies_calls_delete_method_properly(): void
    {
        Gate::define('delete global models', function (User $user) {
            return true;
        });
        $model = Model::factory()
            ->createOne();
        $user = \Illuminate\Support\Facades\Auth::user();

        $policy = new \Lomkit\Access\Tests\Support\Policies\ModelPolicy();

        $this->assertTrue($policy->delete($user, $model));
    }

    public function test_policies_calls_restore_method_properly(): void
    {
        Gate::define('restore global models', function (User $user) {
            return true;
        });
        $model = Model::factory()
            ->createOne();
        $user = \Illuminate\Support\Facades\Auth::user();

        $policy = new \Lomkit\Access\Tests\Support\Policies\ModelPolicy();

        $this->assertTrue($policy->restore($user, $model));
    }

    public function test_policies_calls_force_delete_method_properly(): void
    {
        Gate::define('forceDelete global models', function (User $user) {
            return true;
        });
        $model = Model::factory()
            ->createOne();
        $user = \Illuminate\Support\Facades\Auth::user();

        $policy = new \Lomkit\Access\Tests\Support\Policies\ModelPolicy();

        $this->assertTrue($policy->forceDelete($user, $model));
    }
}

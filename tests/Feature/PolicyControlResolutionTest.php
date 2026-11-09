<?php

namespace Lomkit\Access\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Access;
use Lomkit\Access\Tests\Support\Access\Controls\CountingModelControl;
use Lomkit\Access\Tests\Support\Models\Model;
use Lomkit\Access\Tests\Support\Models\User;
use Lomkit\Access\Tests\Support\Policies\CountingModelPolicy;

class PolicyControlResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        Access::flush();

        parent::setUp();

        Gate::define('view global models', function (User $user) {
            return true;
        });
        Gate::define('update global models', function (User $user) {
            return true;
        });
    }

    protected function tearDown(): void
    {
        Access::flush();

        parent::tearDown();
    }

    public function test_policy_uses_the_registered_control_instance(): void
    {
        $control = new CountingModelControl();
        (new Access())->addControl($control);
        $model = Model::factory()->createOne();
        $user = Auth::user();

        $this->assertTrue((new CountingModelPolicy())->view($user, $model));
        $this->assertTrue((new CountingModelPolicy())->viewAny($user));
        $this->assertTrue((new CountingModelPolicy())->update($user, $model));

        $this->assertSame($control, Access::control(CountingModelControl::class));
        $this->assertSame(1, $control->perimetersBuilt);
    }

    public function test_policy_registers_its_control_when_missing(): void
    {
        $model = Model::factory()->createOne();
        $user = Auth::user();

        $this->assertTrue((new CountingModelPolicy())->view($user, $model));
        $this->assertTrue((new CountingModelPolicy())->view($user, $model));
        $this->assertTrue((new CountingModelPolicy())->viewAny($user));

        $control = Access::control(CountingModelControl::class);

        $this->assertInstanceOf(CountingModelControl::class, $control);
        $this->assertSame(1, $control->perimetersBuilt);
    }
}

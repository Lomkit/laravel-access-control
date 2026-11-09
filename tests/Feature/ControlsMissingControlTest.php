<?php

namespace Lomkit\Access\Tests\Feature;

use Lomkit\Access\Exceptions\MissingControlException;
use Lomkit\Access\Tests\Support\Models\UncontrolledModel;

class ControlsMissingControlTest extends \Lomkit\Access\Tests\Feature\TestCase
{
    public function test_controlled_query_without_control_throws(): void
    {
        $this->expectException(MissingControlException::class);
        $this->expectExceptionMessage(sprintf('No control is registered for the [%s] model.', UncontrolledModel::class));

        UncontrolledModel::query()->controlled();
    }

    public function test_query_enabled_by_default_without_control_throws(): void
    {
        config(['access-control.queries.enabled_by_default' => true]);

        $this->expectException(MissingControlException::class);

        UncontrolledModel::query()->get();
    }

    public function test_scout_controlled_query_without_control_throws(): void
    {
        $this->expectException(MissingControlException::class);
        $this->expectExceptionMessage(sprintf('No control is registered for the [%s] model.', UncontrolledModel::class));

        UncontrolledModel::search()->controlled();
    }

    public function test_uncontrolled_query_without_control_does_not_throw(): void
    {
        config(['access-control.queries.enabled_by_default' => true]);

        $this->assertEquals(0, UncontrolledModel::query()->uncontrolled()->count());
    }
}

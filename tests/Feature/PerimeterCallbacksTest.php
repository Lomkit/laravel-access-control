<?php

namespace Lomkit\Access\Tests\Feature;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Exceptions\MissingPerimeterCallbackException;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Tests\Support\Access\Perimeters\ClientPerimeter;
use Lomkit\Access\Tests\Support\Access\Perimeters\SharedPerimeter;
use Lomkit\Access\Tests\Support\Models\Model as ControlledModel;
use Lomkit\Access\Tests\Support\Models\SearchableModel;
use PHPUnit\Framework\Attributes\Group;

class PerimeterCallbacksTest extends TestCase
{
    /**
     * Build a control over the test model with the given perimeters.
     *
     * @param array<Perimeter> $perimeters
     */
    protected function control(array $perimeters): Control
    {
        return new class($perimeters) extends Control
        {
            protected string $model = ControlledModel::class;

            public function __construct(private array $declaredPerimeters)
            {
            }

            protected function perimeters(): array
            {
                return $this->declaredPerimeters;
            }
        };
    }

    public function test_missing_allowed_callback_throws_when_the_control_applies(): void
    {
        $control = $this->control([
            ClientPerimeter::new()
                ->should(fn (Model $user, Model $model) => true)
                ->query(fn (Builder $query, Model $user) => $query)
                ->scoutQuery(fn (\Laravel\Scout\Builder $query, Model $user) => $query),
        ]);

        $this->expectException(MissingPerimeterCallbackException::class);
        $this->expectExceptionMessage(sprintf('The [%s] perimeter has no [allowed] callback.', ClientPerimeter::class));

        $control->applies(Auth::user(), 'create', new ControlledModel());
    }

    public function test_missing_allowed_callback_throws_when_queried(): void
    {
        $control = $this->control([
            ClientPerimeter::new()
                ->query(fn (Builder $query, Model $user) => $query),
        ]);

        $this->expectException(MissingPerimeterCallbackException::class);
        $this->expectExceptionMessage(sprintf('The [%s] perimeter has no [allowed] callback.', ClientPerimeter::class));

        $control->queried(ControlledModel::query(), Auth::user())->get();
    }

    public function test_missing_query_callback_throws_when_queried(): void
    {
        $control = $this->control([
            ClientPerimeter::new()
                ->allowed(fn (Model $user, string $method) => true)
                ->should(fn (Model $user, Model $model) => true)
                ->scoutQuery(fn (\Laravel\Scout\Builder $query, Model $user) => $query),
        ]);

        $this->expectException(MissingPerimeterCallbackException::class);
        $this->expectExceptionMessage(sprintf('The [%s] perimeter has no [query] callback.', ClientPerimeter::class));

        $control->queried(ControlledModel::query(), Auth::user())->get();
    }

    public function test_missing_query_callback_is_not_needed_when_the_perimeter_is_not_allowed(): void
    {
        ControlledModel::factory()
            ->count(5)
            ->create();

        $control = $this->control([
            ClientPerimeter::new()
                ->allowed(fn (Model $user, string $method) => false),
        ]);

        $this->assertEquals(0, $control->queried(ControlledModel::query(), Auth::user())->count());
    }

    #[Group('scout')]
    public function test_missing_scout_query_callback_throws_when_scout_queried(): void
    {
        $control = $this->control([
            ClientPerimeter::new()
                ->allowed(fn (Model $user, string $method) => true)
                ->should(fn (Model $user, Model $model) => true)
                ->query(fn (Builder $query, Model $user) => $query),
        ]);

        $this->expectException(MissingPerimeterCallbackException::class);
        $this->expectExceptionMessage(sprintf('The [%s] perimeter has no [scoutQuery] callback.', ClientPerimeter::class));

        $control->scoutQueried(SearchableModel::search(), Auth::user());
    }

    public function test_missing_scout_query_callback_does_not_affect_eloquent_queries(): void
    {
        ControlledModel::factory()
            ->count(5)
            ->create();
        ControlledModel::factory()
            ->clientPerimeter()
            ->count(3)
            ->create();

        $control = $this->control([
            ClientPerimeter::new()
                ->allowed(fn (Model $user, string $method) => true)
                ->should(fn (Model $user, Model $model) => true)
                ->query(fn (Builder $query, Model $user) => $query->where('client_id', $user->client->getKey())),
        ]);

        $this->assertEquals(3, $control->queried(ControlledModel::query(), Auth::user())->count());
    }

    public function test_missing_should_callback_throws_when_an_existing_model_is_checked(): void
    {
        $model = ControlledModel::factory()
            ->clientPerimeter()
            ->create();

        $control = $this->control([
            SharedPerimeter::new()
                ->allowed(fn (Model $user, string $method) => true)
                ->query(fn (Builder $query, Model $user) => $query)
                ->scoutQuery(fn (\Laravel\Scout\Builder $query, Model $user) => $query),
        ]);

        $this->expectException(MissingPerimeterCallbackException::class);
        $this->expectExceptionMessage(sprintf('The [%s] perimeter has no [should] callback.', SharedPerimeter::class));

        $control->applies(Auth::user(), 'update', $model);
    }

    public function test_missing_should_callback_does_not_affect_create_and_view_any(): void
    {
        $control = $this->control([
            ClientPerimeter::new()
                ->allowed(fn (Model $user, string $method) => true)
                ->query(fn (Builder $query, Model $user) => $query)
                ->scoutQuery(fn (\Laravel\Scout\Builder $query, Model $user) => $query),
        ]);

        $this->assertTrue($control->applies(Auth::user(), 'create', new ControlledModel()));
        $this->assertTrue($control->applies(Auth::user(), 'viewAny', new ControlledModel()));
    }

    public function test_perimeter_without_any_callback_throws_on_each_use(): void
    {
        $perimeter = Perimeter::new();

        foreach ([
            'allowed'    => fn () => $perimeter->applyAllowedCallback(Auth::user(), 'view'),
            'should'     => fn () => $perimeter->applyShouldCallback(Auth::user(), new ControlledModel()),
            'query'      => fn () => $perimeter->applyQueryCallback(ControlledModel::query(), Auth::user()),
        ] as $callback => $apply) {
            try {
                $apply();

                $this->fail(sprintf('The missing [%s] callback did not throw.', $callback));
            } catch (MissingPerimeterCallbackException $exception) {
                $this->assertStringContainsString(sprintf('The [%s] perimeter has no [%s] callback.', Perimeter::class, $callback), $exception->getMessage());
            }
        }
    }

    #[Group('scout')]
    public function test_perimeter_without_any_callback_throws_when_scout_queried(): void
    {
        $this->expectException(MissingPerimeterCallbackException::class);
        $this->expectExceptionMessage(sprintf('The [%s] perimeter has no [scoutQuery] callback.', Perimeter::class));

        Perimeter::new()->applyScoutQueryCallback(SearchableModel::search(), Auth::user());
    }
}

<?php

namespace Lomkit\Access\Tests\Feature\Perimeters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Exceptions\EmptyQueryCallbackException;
use Lomkit\Access\Exceptions\QueryCallbackReturnedAnotherBuilderException;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\SharedPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class QueryCallbackTest extends PackageTestCase
{
    protected User $ada;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $acme = Client::factory()->createOne(['name' => 'Acme']);
        $globex = Client::factory()->createOne(['name' => 'Globex']);
        $this->ada = User::factory()->for($acme)->createOne(['name' => 'Ada', 'email' => 'ada@example.com']);
        $hank = User::factory()->for($globex)->createOne(['name' => 'Hank', 'email' => 'hank@example.com']);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $warehouse = Project::factory()->for($globex)->createOne(['name' => 'Warehouse']);
        Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Release notes']);
        Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Draft', 'published' => false]);
        Post::factory()->for($warehouse)->for($hank, 'author')->createOne(['title' => 'Roadmap']);
        $inventory = Post::factory()->for($warehouse)->for($hank, 'author')->createOne(['title' => 'Inventory']);
        $inventory->sharedWith()->attach($this->ada);
    }

    protected function controlPostsWith(Closure $query): void
    {
        ConfiguredPostControl::bind(
            OwnPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query($query),
        );
    }

    /** @return list<string> */
    protected function readTitles(): array
    {
        return Post::controlledFor($this->ada)->orderBy('title')->pluck('title')->all();
    }

    #[Test]
    public function it_constrains_the_rows_through_a_callback_that_returns_nothing(): void
    {
        $this->controlPostsWith(function (Builder $query, User $user): void {
            $query->whereBelongsTo($user, 'author');
        });

        $this->assertSame(['Draft', 'Release notes'], $this->readTitles());
    }

    #[Test]
    public function it_accepts_a_callback_that_returns_the_builder_it_receives(): void
    {
        $this->controlPostsWith(fn (Builder $query, User $user): Builder => $query->whereBelongsTo($user, 'author'));

        $this->assertSame(['Draft', 'Release notes'], $this->readTitles());
    }

    #[Test]
    public function it_throws_when_the_callback_returns_another_builder(): void
    {
        $this->controlPostsWith(fn (Builder $query, User $user): Builder => Post::query()->whereBelongsTo($user, 'author'));

        $this->expectException(QueryCallbackReturnedAnotherBuilderException::class);
        $this->expectExceptionMessage(sprintf(
            'The [query] callback of the [%s] perimeter returned another builder for the [%s] model.',
            OwnPerimeter::class,
            Post::class,
        ));

        $this->readTitles();
    }

    #[Test]
    public function it_throws_when_the_callback_constrains_and_returns_another_builder(): void
    {
        $this->controlPostsWith(function (Builder $query, User $user): Builder {
            $query->whereBelongsTo($user, 'author');

            return Post::query();
        });

        $this->expectException(QueryCallbackReturnedAnotherBuilderException::class);

        $this->readTitles();
    }

    #[Test]
    public function it_names_the_perimeter_among_several_when_one_returns_another_builder(): void
    {
        ConfiguredPostControl::bind(
            SharedPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(function (Builder $query, User $user): void {
                    $query->whereHas('sharedWith', fn (Builder $users) => $users->whereKey($user->getKey()));
                }),
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(fn (Builder $query, User $user): Builder => Post::query()->whereHas('project', fn (Builder $projects) => $projects->whereBelongsTo($user->client))),
        );

        $this->expectException(QueryCallbackReturnedAnotherBuilderException::class);
        $this->expectExceptionMessage(sprintf('of the [%s] perimeter', ClientPerimeter::class));

        $this->readTitles();
    }

    /** @return array<string, array{Closure}> */
    public static function emptyCallbacks(): array
    {
        return [
            'returning the builder' => [fn (Builder $query): Builder => $query],
            'doing nothing' => [function (Builder $query): void {}],
            'only ordering' => [function (Builder $query): void {
                $query->orderBy('title');
            }],
            'only limiting' => [function (Builder $query): void {
                $query->limit(1);
            }],
            'only joining' => [function (Builder $query): void {
                $query->join('projects', 'projects.id', '=', 'posts.project_id');
            }],
            'adding an empty group' => [function (Builder $query): void {
                $query->where(fn (Builder $group) => $group);
            }],
            'skipping a conditional constraint' => [function (Builder $query): void {
                $query->when(false, fn (Builder $query) => $query->where('published', true));
            }],
        ];
    }

    #[Test]
    #[DataProvider('emptyCallbacks')]
    public function it_throws_when_the_callback_adds_no_constraint(Closure $query): void
    {
        $this->controlPostsWith($query);

        $this->expectException(EmptyQueryCallbackException::class);
        $this->expectExceptionMessage(sprintf(
            'The [query] callback of the [%s] perimeter in the [%s] control added no constraint to the query it receives',
            OwnPerimeter::class,
            ConfiguredPostControl::class,
        ));
        $this->expectExceptionMessage('Declare ->unrestricted()');

        $this->readTitles();
    }

    #[Test]
    public function it_throws_when_the_global_form_of_0_x_is_combined_with_another_perimeter(): void
    {
        ConfiguredPostControl::bind(
            SharedPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(function (Builder $query, User $user): void {
                    $query->whereHas('sharedWith', fn (Builder $users) => $users->whereKey($user->getKey()));
                }),
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(fn (Builder $query): Builder => $query),
        );

        $this->expectException(EmptyQueryCallbackException::class);
        $this->expectExceptionMessage(sprintf('of the [%s] perimeter', ClientPerimeter::class));

        $this->readTitles();
    }

    /** @return array<string, array{Closure, list<string>}> */
    public static function constrainingCallbacks(): array
    {
        return [
            'a raw where' => [function (Builder $query): void {
                $query->whereRaw('published = ?', [false]);
            }, ['Draft']],
            'a local scope' => [function (Builder $query): void {
                $query->published();
            }, ['Inventory', 'Release notes', 'Roadmap']],
            'a relation existence' => [function (Builder $query, User $user): void {
                $query->whereHas('sharedWith', fn (Builder $users) => $users->whereKey($user->getKey()));
            }, ['Inventory']],
            'a nested group' => [function (Builder $query): void {
                $query->where(fn (Builder $group) => $group->where('title', 'Draft')->orWhere('title', 'Roadmap'));
            }, ['Draft', 'Roadmap']],
            'an order and a where' => [function (Builder $query): void {
                $query->orderBy('title')->where('title', 'Roadmap');
            }, ['Roadmap']],
        ];
    }

    /** @param  list<string>  $titles */
    #[Test]
    #[DataProvider('constrainingCallbacks')]
    public function it_accepts_a_callback_that_adds_a_constraint(Closure $query, array $titles): void
    {
        $this->controlPostsWith($query);

        $this->assertSame($titles, $this->readTitles());
    }
}

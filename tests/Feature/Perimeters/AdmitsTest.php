<?php

namespace Lomkit\Access\Tests\Feature\Perimeters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Exceptions\EmptyQueryCallbackException;
use Lomkit\Access\Exceptions\JoinInPerimeterException;
use Lomkit\Access\Exceptions\MissingPerimeterCallbackException;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\EditorialPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\SharedPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class AdmitsTest extends PackageTestCase
{
    protected User $ada;

    protected Post $releaseNotes;

    protected Post $draft;

    protected Post $roadmap;

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
        $grace = User::factory()->for($acme)->createOne(['name' => 'Grace', 'email' => 'grace@example.com']);
        $hank = User::factory()->for($globex)->createOne(['name' => 'Hank', 'email' => 'hank@example.com']);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $warehouse = Project::factory()->for($globex)->createOne(['name' => 'Warehouse']);
        $this->releaseNotes = Post::factory()->for($website)->for($grace, 'author')->createOne(['title' => 'Release notes']);
        $this->draft = Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Draft']);
        $this->roadmap = Post::factory()->for($warehouse)->for($hank, 'author')->createOne(['title' => 'Roadmap']);
    }

    protected function clientPerimeter(): Perimeter
    {
        return ClientPerimeter::new()
            ->allowed(fn (User $user, Ability $ability): bool => $ability->is('view'))
            ->should(fn (User $user, Post $post): bool => $post->project->client()->is($user->client));
    }

    #[Test]
    public function it_admits_a_row_of_the_perimeter_for_an_allowed_reader(): void
    {
        $this->assertTrue($this->clientPerimeter()->admits($this->ada, Ability::view(), $this->releaseNotes));
    }

    #[Test]
    public function it_does_not_admit_a_row_outside_the_perimeter(): void
    {
        $this->assertFalse($this->clientPerimeter()->admits($this->ada, Ability::view(), $this->roadmap));
    }

    #[Test]
    public function it_does_not_admit_a_row_for_an_ability_the_perimeter_does_not_allow(): void
    {
        $this->assertFalse($this->clientPerimeter()->admits($this->ada, Ability::update(), $this->releaseNotes));
    }

    #[Test]
    public function it_admits_every_row_through_an_unrestricted_perimeter(): void
    {
        $perimeter = GlobalPerimeter::new()
            ->allowed(fn (User $user): bool => true)
            ->unrestricted();

        $this->assertTrue($perimeter->admits($this->ada, Ability::delete(), $this->roadmap));
    }

    #[Test]
    public function it_admits_through_the_hooks_of_a_class(): void
    {
        Gate::define('update editorial posts', fn (User $user): bool => true);

        $this->assertTrue(EditorialPerimeter::new()->admits($this->ada, Ability::update(), $this->draft));
        $this->assertFalse(EditorialPerimeter::new()->admits($this->ada, Ability::update(), $this->releaseNotes));
        $this->assertFalse(EditorialPerimeter::new()->admits($this->ada, Ability::view(), $this->draft));
    }

    protected function ownPerimeterInSql(): Perimeter
    {
        return OwnPerimeter::new()
            ->allowed(fn (User $user): bool => true)
            ->query(function (Builder $query, User $user): void {
                $query->where('author_id', $user->getKey());
            });
    }

    #[Test]
    public function it_checks_the_stored_row_through_the_query_of_a_perimeter_without_should(): void
    {
        DB::enableQueryLog();

        $this->assertTrue($this->ownPerimeterInSql()->admits($this->ada, Ability::update(), $this->draft));
        $this->assertFalse($this->ownPerimeterInSql()->admits($this->ada, Ability::update(), $this->releaseNotes));
        $this->assertCount(2, DB::getQueryLog());
    }

    #[Test]
    public function it_checks_the_stored_values_of_a_row_changed_in_memory(): void
    {
        $this->releaseNotes->author_id = $this->ada->getKey();

        $this->assertFalse($this->ownPerimeterInSql()->admits($this->ada, Ability::update(), $this->releaseNotes));
    }

    #[Test]
    public function it_does_not_admit_an_unsaved_row_through_a_perimeter_without_should_and_runs_no_query(): void
    {
        DB::enableQueryLog();

        $this->assertFalse($this->ownPerimeterInSql()->admits($this->ada, Ability::create(), new Post(['author_id' => $this->ada->getKey()])));
        $this->assertSame([], DB::getQueryLog());
    }

    #[Test]
    public function it_runs_no_query_for_a_reader_the_perimeter_does_not_allow(): void
    {
        $perimeter = OwnPerimeter::new()
            ->allowed(fn (User $user): bool => false)
            ->query(function (Builder $query, User $user): void {
                $query->where('author_id', $user->getKey());
            });
        DB::enableQueryLog();

        $this->assertFalse($perimeter->admits($this->ada, Ability::view(), $this->draft));
        $this->assertSame([], DB::getQueryLog());
    }

    #[Test]
    public function it_throws_when_the_perimeter_has_neither_should_nor_query(): void
    {
        $perimeter = OwnPerimeter::new()->allowed(fn (User $user): bool => true);

        $this->expectException(MissingPerimeterCallbackException::class);
        $this->expectExceptionMessage(sprintf('The [%s] perimeter has no [query] callback.', OwnPerimeter::class));

        $perimeter->admits($this->ada, Ability::view(), $this->draft);
    }

    #[Test]
    public function it_throws_when_the_query_of_a_perimeter_without_should_adds_no_constraint(): void
    {
        $perimeter = OwnPerimeter::new()
            ->allowed(fn (User $user): bool => true)
            ->query(function (Builder $query): void {});

        $this->expectException(EmptyQueryCallbackException::class);
        $this->expectExceptionMessage(sprintf('The [query] callback of the [%s] perimeter added no constraint to the query of the [%s] model', OwnPerimeter::class, Post::class));

        $perimeter->admits($this->ada, Ability::view(), $this->draft);
    }

    #[Test]
    public function it_throws_when_the_query_of_a_perimeter_without_should_adds_a_join(): void
    {
        $perimeter = OwnPerimeter::new()
            ->allowed(fn (User $user): bool => true)
            ->query(function (Builder $query, User $user): void {
                $query->join('projects', 'projects.id', '=', 'posts.project_id')->where('author_id', $user->getKey());
            });

        $this->expectException(JoinInPerimeterException::class);
        $this->expectExceptionMessage(sprintf('The [query] callback of the [%s] perimeter adds a join to the query of the [%s] model.', OwnPerimeter::class, Post::class));

        $perimeter->admits($this->ada, Ability::view(), $this->draft);
    }

    #[Test]
    public function it_lists_the_perimeters_that_apply_to_a_reader_for_an_ability(): void
    {
        $shared = SharedPerimeter::new()->allowed(fn (User $user, Ability $ability): bool => $ability->is('view'));
        $client = $this->clientPerimeter();
        $own = OwnPerimeter::new()->allowed(fn (User $user): bool => true);
        $control = ConfiguredPostControl::bind(Levels::of($client, $own), $shared);

        $this->assertSame([$client, $shared], $control->applicableTo($this->ada, Ability::view()));
        $this->assertSame([$own], $control->applicableTo($this->ada, Ability::update()));
    }

    #[Test]
    public function it_lists_no_perimeter_for_a_reader_none_allows(): void
    {
        $control = ConfiguredPostControl::bind($this->clientPerimeter());

        $this->assertSame([], $control->applicableTo($this->ada, Ability::delete()));
    }

    #[Test]
    public function it_tells_which_applicable_perimeters_admit_a_row(): void
    {
        $shared = SharedPerimeter::new()
            ->allowed(fn (User $user): bool => true)
            ->should(fn (User $user, Post $post): bool => $post->sharedWith()->whereKey($user->getKey())->exists());
        $client = $this->clientPerimeter();
        $control = ConfiguredPostControl::bind($client, $shared);
        $this->roadmap->sharedWith()->attach($this->ada);

        $admitting = fn (Post $post): array => array_values(array_filter(
            $control->applicableTo($this->ada, Ability::view()),
            fn (Perimeter $perimeter): bool => $perimeter->admits($this->ada, Ability::view(), $post),
        ));

        $this->assertSame([$client], $admitting($this->releaseNotes));
        $this->assertSame([$shared], $admitting($this->roadmap));
    }
}

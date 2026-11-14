<?php

namespace Lomkit\Access\Tests\Feature\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Controls\HasControlScope;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class ControlledQueriesTest extends PackageTestCase
{
    protected User $ada;

    protected User $grace;

    protected User $hank;

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
        $this->grace = User::factory()->for($acme)->createOne(['name' => 'Grace', 'email' => 'grace@example.com']);
        $this->hank = User::factory()->for($globex)->createOne(['name' => 'Hank', 'email' => 'hank@example.com']);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $warehouse = Project::factory()->for($globex)->createOne(['name' => 'Warehouse']);
        Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Release notes']);
        Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Draft', 'published' => false]);
        Post::factory()->for($website)->for($this->grace, 'author')->createOne(['title' => 'Roadmap']);
        $inventory = Post::factory()->for($warehouse)->for($this->hank, 'author')->createOne(['title' => 'Inventory']);
        $inventory->sharedWith()->attach($this->ada);
    }

    /** @param  list<string>  $abilities */
    protected function allow(array $abilities): void
    {
        foreach ($abilities as $ability) {
            Gate::define($ability, fn (User $user): bool => true);
        }
    }

    #[Test]
    public function it_reads_the_posts_of_the_signed_in_user(): void
    {
        $this->allow(['view own posts']);

        $this->actingAs($this->ada);

        $this->assertSame(['Draft', 'Release notes'], Post::controlled()->orderBy('title')->pluck('title')->all());
    }

    #[Test]
    public function it_reads_nothing_without_a_signed_in_user(): void
    {
        $this->allow(['view global posts']);

        $this->assertSame(0, Post::controlled()->count());
    }

    #[Test]
    public function it_reads_the_posts_of_a_named_reader_instead_of_the_signed_in_user(): void
    {
        $this->allow(['view own posts']);

        $this->actingAs($this->ada);

        $this->assertSame(['Roadmap'], Post::controlledFor($this->grace)->pluck('title')->all());
    }

    #[Test]
    public function it_reads_the_posts_of_a_named_reader_without_a_signed_in_user(): void
    {
        $this->allow(['view own posts']);

        $this->assertSame(['Inventory'], Post::controlledFor($this->hank)->pluck('title')->all());
    }

    #[Test]
    public function it_reads_nothing_for_a_null_reader_even_with_a_signed_in_user(): void
    {
        $this->allow(['view global posts']);

        $this->actingAs($this->ada);

        $this->assertSame(0, Post::controlledFor(null)->count());
    }

    #[Test]
    public function it_leaves_a_plain_query_unfiltered(): void
    {
        $this->actingAs($this->ada);

        $this->assertSame(4, Post::query()->count());
        $this->assertSame('select * from posts', $this->sql(Post::query()));
    }

    #[Test]
    public function it_keeps_the_control_scope_registered_without_filtering(): void
    {
        $this->assertArrayHasKey(HasControlScope::class, (new Post)->getGlobalScopes());
        $this->assertSame([], Post::query()->removedScopes());
    }

    #[Test]
    public function it_removes_the_control_scope_from_the_queries_that_declare_their_control(): void
    {
        $this->actingAs($this->ada);

        $this->assertSame([HasControlScope::class], Post::controlled()->removedScopes());
        $this->assertSame([HasControlScope::class], Post::controlledFor($this->grace)->removedScopes());
        $this->assertSame([HasControlScope::class], Post::uncontrolled()->removedScopes());
    }

    #[Test]
    public function it_reads_every_post_through_an_uncontrolled_query(): void
    {
        $this->actingAs($this->ada);

        $this->assertSame(4, Post::uncontrolled()->count());
        $this->assertSame('select * from posts', $this->sql(Post::uncontrolled()));
    }

    #[Test]
    public function it_reads_every_post_without_the_control_scope(): void
    {
        $this->actingAs($this->ada);

        $this->assertSame(4, Post::withoutGlobalScope(HasControlScope::class)->count());
    }

    #[Test]
    public function it_never_widens_the_constraints_of_the_caller(): void
    {
        $this->allow(['view shared posts', 'view own posts']);

        $this->actingAs($this->ada);

        $query = Post::where('published', true)->controlled();

        $this->assertSame(['Inventory', 'Release notes'], $query->orderBy('title')->pluck('title')->all());
        $this->assertStringStartsWith('select * from posts where published = ? and ((exists (', $this->sql($query));
    }

    #[Test]
    public function it_keeps_the_or_constraints_of_the_caller_in_their_own_group(): void
    {
        $this->allow(['view own posts']);

        $this->actingAs($this->ada);

        $query = Post::where('published', true)->orWhere('title', 'Roadmap')->controlled();

        $this->assertSame(['Release notes'], $query->pluck('title')->all());
        $this->assertStringStartsWith('select * from posts where (published = ? or title = ?) and ((', $this->sql($query));
    }

    #[Test]
    public function it_controls_a_relation_query(): void
    {
        $this->allow(['view shared posts']);

        $this->actingAs($this->ada);

        $this->assertSame([], $this->hank->posts()->controlledFor($this->grace)->pluck('title')->all());
        $this->assertSame(['Inventory'], $this->hank->posts()->controlled()->pluck('title')->all());
    }

    #[Test]
    public function it_reads_nothing_when_no_perimeter_allows_the_reader(): void
    {
        $this->actingAs($this->ada);

        $this->assertSame(0, Post::controlled()->count());
    }

    #[Test]
    public function it_reads_every_post_through_the_global_perimeter(): void
    {
        $this->allow(['view global posts', 'view own posts']);

        $this->actingAs($this->ada);

        $this->assertSame(4, Post::controlled()->count());
    }

    #[Test]
    public function it_reads_the_posts_of_the_client_of_the_reader(): void
    {
        $this->allow(['view client posts']);

        $this->actingAs($this->ada);

        $this->assertSame(['Draft', 'Release notes', 'Roadmap'], Post::controlled()->orderBy('title')->pluck('title')->all());
    }

    #[Test]
    public function it_adds_the_shared_posts_to_the_client_posts(): void
    {
        $this->allow(['view shared posts', 'view client posts']);

        $this->actingAs($this->ada);

        $this->assertSame(['Draft', 'Inventory', 'Release notes', 'Roadmap'], Post::controlled()->orderBy('title')->pluck('title')->all());
    }

    #[Test]
    public function it_adds_the_shared_posts_to_a_later_perimeter(): void
    {
        $this->allow(['view shared posts', 'view own posts']);

        $this->actingAs($this->ada);

        $this->assertSame(['Draft', 'Inventory', 'Release notes'], Post::controlled()->orderBy('title')->pluck('title')->all());
    }

    #[Test]
    public function it_reads_only_the_shared_posts(): void
    {
        $this->allow(['view shared posts']);

        $this->actingAs($this->ada);

        $this->assertSame(['Inventory'], Post::controlled()->pluck('title')->all());
    }

    /** @param Builder<Post> $query */
    private function sql(Builder $query): string
    {
        return str_replace(['"', '`'], '', $query->toSql());
    }
}

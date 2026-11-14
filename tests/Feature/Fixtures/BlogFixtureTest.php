<?php

namespace Lomkit\Access\Tests\Feature\Fixtures;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Lomkit\Access\Access;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class BlogFixtureTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog];
    }

    #[Test]
    public function it_builds_the_blog_tables(): void
    {
        foreach (['clients', 'users', 'projects', 'posts', 'comments', 'post_shares'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "The {$table} table is missing.");
        }
    }

    #[Test]
    public function it_loads_the_blog_relations(): void
    {
        $client = Client::factory()->createOne(['name' => 'Acme']);
        $author = User::factory()->for($client)->createOne(['name' => 'Ada', 'email' => 'ada@example.com']);
        $reader = User::factory()->for($client)->createOne(['name' => 'Grace', 'email' => 'grace@example.com']);
        $project = Project::factory()->for($client)->createOne(['name' => 'Website']);
        $post = Post::factory()->for($project)->for($author, 'author')->createOne(['title' => 'Release notes']);
        Comment::factory()->for($post)->for($reader, 'author')->createOne(['body' => 'Looks good to me']);
        $post->sharedWith()->attach($reader);

        $loadedPost = Post::query()
            ->with(['project.client', 'author', 'comments.author', 'sharedWith'])
            ->findOrFail($post->getKey());

        $this->assertSame('Acme', $loadedPost->project->client->name);
        $this->assertSame('Ada', $loadedPost->author->name);
        $this->assertSame(['Looks good to me'], $loadedPost->comments->pluck('body')->all());
        $this->assertSame(['Grace'], $loadedPost->comments->pluck('author.name')->all());
        $this->assertSame(['Grace'], $loadedPost->sharedWith->pluck('name')->all());
        $this->assertSame(['Release notes'], $reader->sharedPosts->pluck('title')->all());
    }

    #[Test]
    public function it_links_the_posts_to_their_control(): void
    {
        $this->assertInstanceOf(PostControl::class, Access::controlFor(Post::class));
        $this->assertFalse(Access::hasControl(Comment::class));
    }

    #[Test]
    public function it_scopes_the_posts_to_the_client_of_the_user(): void
    {
        $acme = Client::factory()->createOne(['name' => 'Acme']);
        $globex = Client::factory()->createOne(['name' => 'Globex']);
        $ada = User::factory()->for($acme)->createOne(['name' => 'Ada', 'email' => 'ada@example.com']);
        $hank = User::factory()->for($globex)->createOne(['name' => 'Hank', 'email' => 'hank@example.com']);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $warehouse = Project::factory()->for($globex)->createOne(['name' => 'Warehouse']);
        Post::factory()->for($website)->for($ada, 'author')->createOne(['title' => 'Release notes']);
        Post::factory()->for($website)->for($ada, 'author')->createOne(['title' => 'Roadmap']);
        Post::factory()->for($warehouse)->for($hank, 'author')->createOne(['title' => 'Inventory']);
        Gate::define('view client posts', fn (User $user): bool => true);

        $this->actingAs($ada);

        $this->assertSame(
            ['Release notes', 'Roadmap'],
            Post::query()->controlled()->orderBy('title')->pluck('title')->all()
        );
    }

    #[Test]
    public function it_authorizes_the_own_posts_through_the_policy(): void
    {
        $acme = Client::factory()->createOne(['name' => 'Acme']);
        $ada = User::factory()->for($acme)->createOne(['name' => 'Ada', 'email' => 'ada@example.com']);
        $grace = User::factory()->for($acme)->createOne(['name' => 'Grace', 'email' => 'grace@example.com']);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $releaseNotes = Post::factory()->for($website)->for($ada, 'author')->createOne(['title' => 'Release notes']);
        $roadmap = Post::factory()->for($website)->for($grace, 'author')->createOne(['title' => 'Roadmap']);
        Gate::define('update own posts', fn (User $user): bool => true);

        $this->assertTrue($ada->can('update', $releaseNotes));
        $this->assertFalse($ada->can('update', $roadmap));
        $this->assertFalse($ada->can('delete', $releaseNotes));
    }
}

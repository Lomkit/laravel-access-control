<?php

namespace Lomkit\Access\Tests\Feature\Scopes;

use Lomkit\Access\Exceptions\MissingControlException;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class MissingControlTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog];
    }

    #[Test]
    public function it_throws_on_a_controlled_query_without_a_control(): void
    {
        $this->actingAs(User::factory()->createOne());

        $this->expectException(MissingControlException::class);
        $this->expectExceptionMessage(sprintf('No control is registered for the [%s] model.', Comment::class));

        Comment::controlled();
    }

    #[Test]
    public function it_throws_on_a_query_for_a_named_reader_without_a_control(): void
    {
        $this->expectException(MissingControlException::class);
        $this->expectExceptionMessage(sprintf('No control is registered for the [%s] model.', Comment::class));

        Comment::controlledFor(User::factory()->createOne());
    }

    #[Test]
    public function it_throws_on_a_query_for_a_null_reader_without_a_control(): void
    {
        $this->expectException(MissingControlException::class);

        Comment::controlledFor(null);
    }

    #[Test]
    public function it_runs_plain_and_uncontrolled_queries_without_a_control(): void
    {
        $post = Post::factory()->for(Project::factory())->createOne(['title' => 'Release notes']);
        Comment::factory()->for($post)->createOne(['body' => 'Looks good to me']);

        $this->assertSame(['Looks good to me'], Comment::query()->pluck('body')->all());
        $this->assertSame(['Looks good to me'], Comment::uncontrolled()->pluck('body')->all());
    }
}

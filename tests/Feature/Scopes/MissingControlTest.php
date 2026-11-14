<?php

namespace Lomkit\Access\Tests\Feature\Scopes;

use Lomkit\Access\Exceptions\MissingControlException;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
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
        return [
            new class extends Blog
            {
                #[\Override]
                public function controls(): array
                {
                    return [];
                }
            },
        ];
    }

    #[Test]
    public function it_throws_on_a_controlled_query_without_a_control(): void
    {
        $this->actingAs(User::factory()->createOne());

        $this->expectException(MissingControlException::class);
        $this->expectExceptionMessage(sprintf('No control is registered for the [%s] model.', Post::class));

        Post::controlled();
    }

    #[Test]
    public function it_throws_on_a_query_for_a_named_reader_without_a_control(): void
    {
        $this->expectException(MissingControlException::class);
        $this->expectExceptionMessage(sprintf('No control is registered for the [%s] model.', Post::class));

        Post::controlledFor(User::factory()->createOne());
    }

    #[Test]
    public function it_throws_on_a_query_for_a_null_reader_without_a_control(): void
    {
        $this->expectException(MissingControlException::class);

        Post::controlledFor(null);
    }

    #[Test]
    public function it_runs_plain_and_uncontrolled_queries_without_a_control(): void
    {
        Post::factory()->for(Project::factory())->createOne(['title' => 'Release notes']);

        $this->assertSame(['Release notes'], Post::query()->pluck('title')->all());
        $this->assertSame(['Release notes'], Post::uncontrolled()->pluck('title')->all());
    }
}

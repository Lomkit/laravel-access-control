<?php

namespace Lomkit\Access\Tests\Feature\Policies;

use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Exceptions\MissingControlException;
use Lomkit\Access\Exceptions\PolicyControlMismatchException;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\MislinkedPostPolicy;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\PostPolicy;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Blog\UserControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class PolicyControlTest extends PackageTestCase
{
    protected User $ada;

    protected Post $releaseNotes;

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
        $this->ada = User::factory()->for($acme)->createOne(['name' => 'Ada', 'email' => 'ada@example.com']);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $this->releaseNotes = Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Release notes']);
        Gate::define('view own posts', fn (User $user): bool => true);
        Gate::define('view own users', fn (User $user): bool => true);
    }

    #[Test]
    public function it_decides_with_the_control_of_the_model(): void
    {
        $this->assertTrue($this->ada->can('view', $this->releaseNotes));
        $this->assertTrue($this->ada->can('viewAny', Post::class));
    }

    #[Test]
    public function it_throws_when_the_policy_names_another_control_than_the_model(): void
    {
        Gate::policy(Post::class, MislinkedPostPolicy::class);

        $this->expectException(PolicyControlMismatchException::class);
        $this->expectExceptionMessage(sprintf(
            'The [%s] policy names the [%s] control, but the [%s] model is controlled by [%s].',
            MislinkedPostPolicy::class,
            UserControl::class,
            Post::class,
            PostControl::class,
        ));

        $this->ada->can('view', $this->releaseNotes);
    }

    #[Test]
    public function it_throws_when_the_policy_decides_for_a_model_of_another_control(): void
    {
        Gate::policy(User::class, PostPolicy::class);

        $this->expectException(PolicyControlMismatchException::class);
        $this->expectExceptionMessage(sprintf(
            'The [%s] policy names the [%s] control, but the [%s] model is controlled by [%s].',
            PostPolicy::class,
            PostControl::class,
            User::class,
            UserControl::class,
        ));

        $this->ada->can('update', $this->ada);
    }

    #[Test]
    public function it_throws_when_the_policy_decides_for_a_model_without_control(): void
    {
        Gate::policy(Comment::class, PostPolicy::class);
        $comment = Comment::factory()->for($this->releaseNotes)->for($this->ada, 'author')->createOne();

        $this->expectException(MissingControlException::class);
        $this->expectExceptionMessage(Comment::class);

        $this->ada->can('delete', $comment);
    }
}

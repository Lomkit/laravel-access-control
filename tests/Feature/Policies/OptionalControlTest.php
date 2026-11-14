<?php

namespace Lomkit\Access\Tests\Feature\Policies;

use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Exceptions\MissingPolicyControlException;
use Lomkit\Access\Exceptions\PolicyControlMismatchException;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\PostPolicy;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\UndeclaredPostPolicy;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Blog\UserControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class OptionalControlTest extends PackageTestCase
{
    protected User $ada;

    protected Post $releaseNotes;

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
        $hank = User::factory()->for($globex)->createOne(['name' => 'Hank', 'email' => 'hank@example.com']);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $warehouse = Project::factory()->for($globex)->createOne(['name' => 'Warehouse']);
        $this->releaseNotes = Post::factory()->for($website)->for($hank, 'author')->createOne(['title' => 'Release notes']);
        $this->roadmap = Post::factory()->for($warehouse)->for($hank, 'author')->createOne(['title' => 'Roadmap']);
        Gate::policy(Post::class, UndeclaredPostPolicy::class);
        Gate::define('update client posts', fn (User $user): bool => true);
        Gate::define('create client posts', fn (User $user): bool => true);
    }

    #[Test]
    public function it_decides_a_row_with_the_control_of_its_model(): void
    {
        $this->assertTrue($this->ada->can('update', $this->releaseNotes));
        $this->assertFalse($this->ada->can('update', $this->roadmap));
    }

    #[Test]
    public function it_decides_a_draft_with_the_control_of_its_model(): void
    {
        $draft = new Post(['project_id' => $this->releaseNotes->project_id, 'author_id' => $this->ada->getKey(), 'title' => 'Draft', 'workspace_id' => 1]);

        $this->assertTrue($this->ada->can('create', [Post::class, $draft]));
    }

    #[Test]
    public function it_throws_without_a_row_when_the_policy_names_no_control(): void
    {
        $this->expectException(MissingPolicyControlException::class);
        $this->expectExceptionMessage(sprintf('The [%s] policy cannot decide [create] without a row, since it names no control.', UndeclaredPostPolicy::class));

        $this->ada->can('create', Post::class);
    }

    #[Test]
    public function it_decides_without_a_row_with_the_control_the_policy_names(): void
    {
        Gate::policy(Post::class, PostPolicy::class);

        $this->assertTrue($this->ada->can('create', Post::class));
    }

    #[Test]
    public function it_throws_when_a_draft_belongs_to_a_model_of_another_control(): void
    {
        Gate::policy(Post::class, PostPolicy::class);

        $this->expectException(PolicyControlMismatchException::class);
        $this->expectExceptionMessage(sprintf(
            'The [%s] policy names the [%s] control, but the [%s] model is controlled by [%s].',
            PostPolicy::class,
            PostControl::class,
            User::class,
            UserControl::class,
        ));

        $this->ada->can('create', [Post::class, new User]);
    }
}

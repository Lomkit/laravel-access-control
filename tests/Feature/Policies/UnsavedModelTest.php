<?php

namespace Lomkit\Access\Tests\Feature\Policies;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class UnsavedModelTest extends PackageTestCase
{
    protected User $ada;

    protected Post $unsaved;

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
        $this->ada = User::factory()->for($acme)->createOne(['name' => 'Ada', 'email' => 'ada@example.com', 'current_workspace_id' => 1]);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $this->unsaved = new Post([
            'project_id' => $website->getKey(),
            'author_id' => $this->ada->getKey(),
            'title' => 'Unsaved',
            'workspace_id' => 1,
        ]);
    }

    /** @return array<string, array{string}> */
    public static function abilities(): array
    {
        return [
            'view' => ['view'],
            'update' => ['update'],
            'delete' => ['delete'],
            'restore' => ['restore'],
            'forceDelete' => ['forceDelete'],
            'approve' => ['approve'],
        ];
    }

    #[Test]
    #[DataProvider('abilities')]
    public function it_refuses_an_unsaved_model_without_any_query(string $ability): void
    {
        foreach (['global', 'client', 'own'] as $level) {
            Gate::define("{$ability} {$level} posts", fn (User $user): bool => true);
        }

        DB::enableQueryLog();
        $response = Gate::forUser($this->ada)->inspect($ability, $this->unsaved);
        DB::disableQueryLog();

        $this->assertTrue($response->denied());
        $this->assertSame('unsaved', $response->code());
        $this->assertSame([], DB::getQueryLog());
    }

    #[Test]
    #[DataProvider('abilities')]
    public function it_refuses_an_unsaved_model_before_checking_the_permissions(string $ability): void
    {
        $this->assertSame('unsaved', Gate::forUser($this->ada)->inspect($ability, $this->unsaved)->code());
    }

    #[Test]
    public function it_refuses_an_unsaved_model_given_to_the_control(): void
    {
        Gate::define('update global posts', fn (User $user): bool => true);
        $control = Access::control(PostControl::class);

        $this->assertFalse($control->applies($this->ada, Ability::update(), $this->unsaved));
        $this->assertFalse($control->applies($this->ada, Ability::update(), new Post));
    }

    #[Test]
    public function it_decides_view_any_and_create_without_a_saved_row(): void
    {
        Gate::define('view own posts', fn (User $user): bool => true);
        Gate::define('create own posts', fn (User $user): bool => true);

        $this->assertTrue($this->ada->can('viewAny', Post::class));
        $this->assertTrue($this->ada->can('create', Post::class));
        $this->assertTrue($this->ada->can('create', [Post::class, $this->unsaved]));
    }

    #[Test]
    public function it_decides_a_model_again_once_it_is_saved(): void
    {
        Gate::define('update own posts', fn (User $user): bool => true);

        $this->assertFalse($this->ada->can('update', $this->unsaved));

        $this->unsaved->save();

        $this->assertTrue($this->ada->can('update', $this->unsaved));
    }
}

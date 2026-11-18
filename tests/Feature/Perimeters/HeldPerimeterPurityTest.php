<?php

namespace Lomkit\Access\Tests\Feature\Perimeters;

use Lomkit\Access\Exceptions\ImpurePerimetersException;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Purity\BoundScopePostControl;
use Lomkit\Access\Tests\Fixtures\Purity\CollectedPostControl;
use Lomkit\Access\Tests\Fixtures\Purity\CountingUser;
use Lomkit\Access\Tests\Fixtures\Purity\SelfWrappedPostControl;
use Lomkit\Access\Tests\Fixtures\Purity\SharedChildPostControl;
use Lomkit\Access\Tests\Fixtures\Purity\WrappedLevelsPostControl;
use Lomkit\Access\Tests\Fixtures\Purity\WrappedParentPostControl;
use Lomkit\Access\Tests\Fixtures\Purity\WrappedPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class HeldPerimeterPurityTest extends PackageTestCase
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
        $this->ada = User::factory()->for($acme)->createOne(['name' => 'Ada', 'email' => 'ada@example.com']);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Draft']);
        Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Roadmap']);
    }

    protected function expectImpure(string $control): void
    {
        $this->expectException(ImpurePerimetersException::class);
        $this->expectExceptionMessage(sprintf('The perimeters() or boundary() method of the [%s] control built a different declaration twice.', $control));
    }

    #[Test]
    public function it_accepts_a_perimeter_held_by_another_one_built_alike_on_every_build(): void
    {
        config(['posts.title' => 'Draft']);
        $this->app->instance(PostControl::class, new WrappedPostControl);

        for ($lookup = 0; $lookup < 3; $lookup++) {
            $this->assertSame(['Draft'], Post::controlledFor($this->ada)->pluck('title')->all());
        }
    }

    #[Test]
    public function it_catches_a_perimeter_held_by_another_one_that_captures_another_value(): void
    {
        config(['posts.title' => 'Draft']);
        $this->app->instance(PostControl::class, new WrappedPostControl);

        $this->assertSame(['Draft'], Post::controlledFor($this->ada)->pluck('title')->all());

        config(['posts.title' => 'Roadmap']);

        $this->expectImpure(WrappedPostControl::class);

        Post::controlledFor($this->ada)->get();
    }

    #[Test]
    public function it_accepts_a_levels_group_held_by_a_perimeter_built_alike_on_every_build(): void
    {
        config(['posts.title' => 'Draft']);
        $this->app->instance(PostControl::class, new WrappedLevelsPostControl);

        for ($lookup = 0; $lookup < 3; $lookup++) {
            $this->assertSame(['Draft'], Post::controlledFor($this->ada)->pluck('title')->all());
        }
    }

    #[Test]
    public function it_catches_a_levels_group_held_by_a_perimeter_whose_level_captures_another_value(): void
    {
        config(['posts.title' => 'Draft']);
        $this->app->instance(PostControl::class, new WrappedLevelsPostControl);

        $this->assertSame(['Draft'], Post::controlledFor($this->ada)->pluck('title')->all());

        config(['posts.title' => 'Roadmap']);

        $this->expectImpure(WrappedLevelsPostControl::class);

        Post::controlledFor($this->ada)->get();
    }

    #[Test]
    public function it_compares_a_parent_perimeter_held_by_another_one_by_its_declaration(): void
    {
        config(['posts.parent' => 'project']);
        $this->app->instance(PostControl::class, $control = new WrappedParentPostControl);
        $control->verifyPerimeters();
        $control->verifyPerimeters();

        config(['posts.parent' => 'author']);

        $this->expectImpure(WrappedParentPostControl::class);

        $control->verifyPerimeters();
    }

    #[Test]
    public function it_accepts_a_held_perimeter_that_leads_back_to_its_holder(): void
    {
        config(['posts.title' => 'Draft']);
        $this->app->instance(PostControl::class, new SelfWrappedPostControl);

        for ($lookup = 0; $lookup < 3; $lookup++) {
            $this->assertSame(['Draft'], Post::controlledFor($this->ada)->pluck('title')->all());
        }
    }

    #[Test]
    public function it_catches_a_held_perimeter_that_leads_back_to_its_holder_and_captures_another_value(): void
    {
        config(['posts.title' => 'Draft']);
        $this->app->instance(PostControl::class, new SelfWrappedPostControl);

        $this->assertSame(['Draft'], Post::controlledFor($this->ada)->pluck('title')->all());

        config(['posts.title' => 'Roadmap']);

        $this->expectImpure(SelfWrappedPostControl::class);

        Post::controlledFor($this->ada)->get();
    }

    #[Test]
    public function it_catches_a_perimeter_held_in_a_collection_that_captures_another_value(): void
    {
        config(['posts.title' => 'Draft']);
        $this->app->instance(PostControl::class, new CollectedPostControl);

        $this->assertSame(['Draft'], Post::controlledFor($this->ada)->pluck('title')->all());
        $this->assertSame(['Draft'], Post::controlledFor($this->ada)->pluck('title')->all());

        config(['posts.title' => 'Roadmap']);

        $this->expectImpure(CollectedPostControl::class);

        Post::controlledFor($this->ada)->get();
    }

    #[Test]
    public function it_catches_a_callback_bound_to_a_perimeter_whose_state_changes(): void
    {
        config(['posts.title' => 'Draft']);
        $this->app->instance(PostControl::class, new BoundScopePostControl);

        $this->assertSame(['Draft'], Post::controlledFor($this->ada)->pluck('title')->all());
        $this->assertSame(['Draft'], Post::controlledFor($this->ada)->pluck('title')->all());

        config(['posts.title' => 'Roadmap']);

        $this->expectImpure(BoundScopePostControl::class);

        Post::controlledFor($this->ada)->get();
    }

    #[Test]
    public function it_compares_a_child_that_a_chain_of_perimeters_holds_twice_at_each_step_once(): void
    {
        config(['posts.title' => 'Draft', 'posts.depth' => 16, 'posts.owner' => (new CountingUser)->forceFill(['id' => $this->ada->getKey()])]);
        $this->app->instance(PostControl::class, $control = new SharedChildPostControl);
        $control->declaration();
        CountingUser::$comparisons = 0;

        $control->verifyPerimeters();

        $this->assertSame(1, CountingUser::$comparisons);

        config(['posts.title' => 'Roadmap']);

        $this->expectImpure(SharedChildPostControl::class);

        $control->verifyPerimeters();
    }
}

<?php

namespace Lomkit\Access\Tests\Feature\Perimeters;

use Illuminate\Http\Request;
use Lomkit\Access\Exceptions\ImpurePerimetersException;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Purity\RequestBoundaryPostControl;
use Lomkit\Access\Tests\Fixtures\Purity\WorkspaceBoundPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class BoundaryPurityTest extends PackageTestCase
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
    public function it_accepts_a_boundary_class_whose_state_is_built_alike_on_every_build(): void
    {
        $this->app->instance('request', Request::create('/posts', 'GET', ['title' => 'Draft']));
        $this->app->instance(PostControl::class, new RequestBoundaryPostControl);

        for ($lookup = 0; $lookup < 3; $lookup++) {
            $this->assertSame(['Draft'], Post::controlledFor($this->ada)->pluck('title')->all());
        }
    }

    #[Test]
    public function it_catches_a_boundary_class_whose_state_captures_a_value_of_the_request(): void
    {
        $this->app->instance('request', Request::create('/posts', 'GET', ['title' => 'Draft']));
        $this->app->instance(PostControl::class, new RequestBoundaryPostControl);

        $this->assertSame(['Draft'], Post::controlledFor($this->ada)->pluck('title')->all());

        $this->app->instance('request', Request::create('/posts', 'GET', ['title' => 'Roadmap']));

        $this->expectImpure(RequestBoundaryPostControl::class);

        Post::controlledFor($this->ada)->get();
    }

    #[Test]
    public function it_still_compares_a_column_boundary_by_its_column_and_reader_value(): void
    {
        $this->actingAs($this->ada);
        $this->app->instance(PostControl::class, $control = new WorkspaceBoundPostControl);

        $control->verifyPerimeters();
        $control->verifyPerimeters();

        $this->ada->forceFill(['current_workspace_id' => 7]);

        $this->expectImpure(WorkspaceBoundPostControl::class);

        $control->verifyPerimeters();
    }
}

<?php

namespace Lomkit\Access\Tests\Feature\Perimeters;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\ImpurePerimetersException;
use Lomkit\Access\Registry\ControlRegistry;
use Lomkit\Access\Registry\PerimeterPurity;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Purity\CountingPostControl;
use Lomkit\Access\Tests\Fixtures\Purity\PurePostControl;
use Lomkit\Access\Tests\Fixtures\Purity\ReaderBoundPostControl;
use Lomkit\Access\Tests\Fixtures\Purity\RequestBoundPostControl;
use Lomkit\Access\Tests\Fixtures\Purity\ServicePostControl;
use Lomkit\Access\Tests\Fixtures\Purity\SignedInPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class PerimeterPurityTest extends PackageTestCase
{
    protected User $ada;

    protected User $hank;

    protected Post $draft;

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
        $this->hank = User::factory()->for($globex)->createOne(['name' => 'Hank', 'email' => 'hank@example.com']);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $warehouse = Project::factory()->for($globex)->createOne(['name' => 'Warehouse']);
        $this->draft = Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Draft']);
        Post::factory()->for($warehouse)->for($this->hank, 'author')->createOne(['title' => 'Roadmap']);
    }

    protected function expectImpure(string $control): void
    {
        $this->expectException(ImpurePerimetersException::class);
        $this->expectExceptionMessage(sprintf('The perimeters() method of the [%s] control built different perimeters twice.', $control));
    }

    #[Test]
    public function it_catches_perimeters_that_capture_the_signed_in_user_once_another_user_signs_in(): void
    {
        $this->app->instance(PostControl::class, new ReaderBoundPostControl);
        $this->actingAs($this->ada);

        $this->assertSame(['Draft'], Post::controlled()->pluck('title')->all());

        $this->actingAs($this->hank);

        $this->expectImpure(ReaderBoundPostControl::class);

        Post::controlled()->get();
    }

    #[Test]
    public function it_catches_a_perimeter_list_that_depends_on_the_signed_in_user(): void
    {
        $this->app->instance(PostControl::class, new SignedInPostControl);

        $this->assertFalse($this->ada->can('view', $this->draft));

        $this->actingAs($this->ada);

        $this->expectImpure(SignedInPostControl::class);

        $this->ada->can('view', $this->draft);
    }

    #[Test]
    public function it_catches_perimeters_that_capture_a_value_of_the_request(): void
    {
        $this->app->instance(PostControl::class, new RequestBoundPostControl);
        $this->app->instance('request', Request::create('/posts', 'GET', ['title' => 'Draft']));

        $this->assertSame(['Draft'], Post::controlledFor($this->ada)->pluck('title')->all());

        $this->app->instance('request', Request::create('/posts', 'GET', ['title' => 'Roadmap']));

        $this->expectImpure(RequestBoundPostControl::class);

        Post::controlledFor($this->ada)->get();
    }

    #[Test]
    public function it_accepts_perimeters_that_capture_a_service_built_with_them(): void
    {
        $this->app->instance(PostControl::class, new ServicePostControl);

        for ($lookup = 0; $lookup < 3; $lookup++) {
            $this->assertSame(['Draft'], Post::controlledFor($this->ada)->pluck('title')->all());
            $this->assertSame(['Roadmap'], Post::controlledFor($this->hank)->pluck('title')->all());
        }
    }

    #[Test]
    public function it_accepts_perimeters_that_capture_the_same_model_on_every_build(): void
    {
        $this->app->instance(PostControl::class, new ReaderBoundPostControl);
        $this->actingAs($this->ada);

        $this->assertSame(['Draft'], Post::controlled()->pluck('title')->all());

        $this->actingAs(User::query()->findOrFail($this->ada->getKey()));

        $this->assertSame(['Draft'], Post::controlled()->pluck('title')->all());
    }

    #[Test]
    public function it_catches_perimeters_that_capture_another_value_at_each_build(): void
    {
        $this->app->instance(PostControl::class, $control = new CountingPostControl);

        try {
            Access::control(PostControl::class);

            $this->fail('The perimeters built twice were not compared.');
        } catch (ImpurePerimetersException) {
            $this->assertSame(2, $control->builds);
        }
    }

    #[Test]
    public function it_keeps_the_perimeters_it_built_first_when_the_check_fails(): void
    {
        $this->app->instance(PostControl::class, $control = new ReaderBoundPostControl);
        $this->actingAs($this->ada);
        Post::controlled()->get();
        $this->actingAs($this->hank);

        try {
            Post::controlled()->get();

            $this->fail('The perimeters built for another user were not caught.');
        } catch (ImpurePerimetersException) {
            $this->assertSame(['Draft'], Post::uncontrolled()->tap(fn ($query) => $control->restrict($query, $this->hank))->pluck('title')->all());
        }
    }

    #[Test]
    public function it_accepts_perimeters_that_capture_value_objects_enums_and_closures_built_alike(): void
    {
        $this->app->instance(PostControl::class, new PurePostControl);

        for ($lookup = 0; $lookup < 3; $lookup++) {
            $this->assertSame(['Draft'], Post::controlledFor($this->ada)->pluck('title')->all());
        }
    }

    #[Test]
    public function it_accepts_the_perimeters_of_the_blog_on_every_lookup(): void
    {
        for ($lookup = 0; $lookup < 3; $lookup++) {
            $this->assertSame([], Post::controlledFor($this->ada)->pluck('title')->all());
            $this->assertFalse($this->ada->can('view', $this->draft));
        }
    }

    #[Test]
    public function it_verifies_the_perimeters_in_debug_mode_and_in_tests_only(): void
    {
        $this->assertSame(PerimeterPurity::Verified, PerimeterPurity::for($this->app));

        $this->app['env'] = 'production';
        $this->app['config']->set('app.debug', true);

        $this->assertSame(PerimeterPurity::Verified, PerimeterPurity::for($this->app));

        $this->app['config']->set('app.debug', false);

        $this->assertSame(PerimeterPurity::Trusted, PerimeterPurity::for($this->app));
    }

    #[Test]
    public function it_does_not_rebuild_the_perimeters_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->app['config']->set('app.debug', false);
        $this->app->forgetInstance(ControlRegistry::class);
        $this->app->instance(PostControl::class, $control = new CountingPostControl);

        Post::controlledFor($this->ada)->get();
        Post::controlledFor($this->hank)->get();
        Auth::logout();

        $this->assertSame(1, $control->builds);
    }
}

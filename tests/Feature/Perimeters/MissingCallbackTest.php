<?php

namespace Lomkit\Access\Tests\Feature\Perimeters;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Ability;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Decisions\Draft;
use Lomkit\Access\Exceptions\MissingPerimeterCallbackException;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class MissingCallbackTest extends PackageTestCase
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
    }

    protected function expectMissing(string $perimeter, string $callback): void
    {
        $this->expectException(MissingPerimeterCallbackException::class);
        $this->expectExceptionMessage(sprintf('The [%s] perimeter has no [%s] callback.', $perimeter, $callback));
    }

    #[Test]
    public function it_throws_when_the_allowed_callback_is_missing_from_a_query(): void
    {
        ConfiguredPostControl::bind(ClientPerimeter::new()->query(fn (Builder $query) => $query->whereRaw('1=1')));

        $this->expectMissing(ClientPerimeter::class, 'allowed');

        Post::controlledFor($this->ada)->get();
    }

    #[Test]
    public function it_throws_when_the_allowed_callback_is_missing_from_a_decision(): void
    {
        $control = ConfiguredPostControl::bind(ClientPerimeter::new()->unrestricted());

        $this->expectMissing(ClientPerimeter::class, 'allowed');

        $control->applies($this->ada, Ability::create());
    }

    #[Test]
    public function it_throws_when_the_query_callback_of_an_allowed_perimeter_is_missing(): void
    {
        ConfiguredPostControl::bind(ClientPerimeter::new()->allowed(fn (User $user): bool => true));

        $this->expectMissing(ClientPerimeter::class, 'query');

        Post::controlledFor($this->ada)->get();
    }

    #[Test]
    public function it_needs_no_query_callback_on_a_perimeter_that_does_not_allow_the_reader(): void
    {
        ConfiguredPostControl::bind(ClientPerimeter::new()->allowed(fn (User $user): bool => false));

        $this->assertSame(0, Post::controlledFor($this->ada)->count());
    }

    #[Test]
    public function it_throws_when_a_saved_row_is_checked_in_sql_on_a_perimeter_without_a_query_callback(): void
    {
        $control = ConfiguredPostControl::bind(ClientPerimeter::new()->allowed(fn (User $user): bool => true));

        $this->expectMissing(ClientPerimeter::class, 'query');

        $control->applies($this->ada, Ability::update(), $this->releaseNotes);
    }

    #[Test]
    public function it_needs_no_should_callback_without_a_row(): void
    {
        $control = ConfiguredPostControl::bind(ClientPerimeter::new()->allowed(fn (User $user): bool => true));

        $this->assertTrue($control->applies($this->ada, Ability::create()));
        $this->assertTrue($control->applies($this->ada, Ability::view()));
        $this->assertFalse($control->applies($this->ada, Ability::create(), Draft::of(new Post)));
    }

    #[Test]
    public function it_throws_on_each_use_of_a_perimeter_without_callbacks(): void
    {
        $perimeter = Perimeter::new();
        $context = AccessContext::of($this->ada, Ability::view());

        foreach ([
            ['allowed', fn () => $perimeter->applyAllowedCallback($context)],
            ['should', fn () => $perimeter->applyShouldCallback($this->releaseNotes, $context)],
            ['query', fn () => $perimeter->applyQueryCallback(Post::query(), $context)],
            ['allowed', fn () => $perimeter->admits($this->ada, Ability::view(), $this->releaseNotes)],
        ] as [$callback, $apply]) {
            try {
                $apply();

                $this->fail(sprintf('The missing [%s] callback did not throw.', $callback));
            } catch (MissingPerimeterCallbackException $exception) {
                $this->assertStringContainsString(sprintf('The [%s] perimeter has no [%s] callback.', Perimeter::class, $callback), $exception->getMessage());
            }
        }
    }
}

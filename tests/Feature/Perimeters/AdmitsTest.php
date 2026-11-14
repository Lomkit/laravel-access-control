<?php

namespace Lomkit\Access\Tests\Feature\Perimeters;

use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Exceptions\MissingPerimeterCallbackException;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\EditorialPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\SharedPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class AdmitsTest extends PackageTestCase
{
    protected User $ada;

    protected Post $releaseNotes;

    protected Post $draft;

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
        $grace = User::factory()->for($acme)->createOne(['name' => 'Grace', 'email' => 'grace@example.com']);
        $hank = User::factory()->for($globex)->createOne(['name' => 'Hank', 'email' => 'hank@example.com']);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $warehouse = Project::factory()->for($globex)->createOne(['name' => 'Warehouse']);
        $this->releaseNotes = Post::factory()->for($website)->for($grace, 'author')->createOne(['title' => 'Release notes']);
        $this->draft = Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Draft']);
        $this->roadmap = Post::factory()->for($warehouse)->for($hank, 'author')->createOne(['title' => 'Roadmap']);
    }

    protected function clientPerimeter(): Perimeter
    {
        return ClientPerimeter::new()
            ->allowed(fn (User $user, Ability $ability): bool => $ability->is('view'))
            ->should(fn (User $user, Post $post): bool => $post->project->client()->is($user->client));
    }

    #[Test]
    public function it_admits_a_row_of_the_perimeter_for_an_allowed_reader(): void
    {
        $this->assertTrue($this->clientPerimeter()->admits($this->ada, Ability::view(), $this->releaseNotes));
    }

    #[Test]
    public function it_does_not_admit_a_row_outside_the_perimeter(): void
    {
        $this->assertFalse($this->clientPerimeter()->admits($this->ada, Ability::view(), $this->roadmap));
    }

    #[Test]
    public function it_does_not_admit_a_row_for_an_ability_the_perimeter_does_not_allow(): void
    {
        $this->assertFalse($this->clientPerimeter()->admits($this->ada, Ability::update(), $this->releaseNotes));
    }

    #[Test]
    public function it_admits_every_row_through_an_unrestricted_perimeter(): void
    {
        $perimeter = GlobalPerimeter::new()
            ->allowed(fn (User $user): bool => true)
            ->unrestricted();

        $this->assertTrue($perimeter->admits($this->ada, Ability::delete(), $this->roadmap));
    }

    #[Test]
    public function it_admits_through_the_hooks_of_a_class(): void
    {
        Gate::define('update editorial posts', fn (User $user): bool => true);

        $this->assertTrue(EditorialPerimeter::new()->admits($this->ada, Ability::update(), $this->draft));
        $this->assertFalse(EditorialPerimeter::new()->admits($this->ada, Ability::update(), $this->releaseNotes));
        $this->assertFalse(EditorialPerimeter::new()->admits($this->ada, Ability::view(), $this->draft));
    }

    #[Test]
    public function it_throws_when_the_perimeter_has_no_row_check(): void
    {
        $perimeter = OwnPerimeter::new()->allowed(fn (User $user): bool => true);

        $this->expectException(MissingPerimeterCallbackException::class);
        $this->expectExceptionMessage(sprintf('The [%s] perimeter has no [should] callback.', OwnPerimeter::class));

        $perimeter->admits($this->ada, Ability::view(), $this->draft);
    }

    #[Test]
    public function it_lists_the_perimeters_that_apply_to_a_reader_for_an_ability(): void
    {
        $shared = SharedPerimeter::new()->allowed(fn (User $user, Ability $ability): bool => $ability->is('view'));
        $client = $this->clientPerimeter();
        $own = OwnPerimeter::new()->allowed(fn (User $user): bool => true);
        $control = ConfiguredPostControl::bind(Levels::of($client, $own), $shared);

        $this->assertSame([$client, $shared], $control->applicableTo($this->ada, Ability::view()));
        $this->assertSame([$own], $control->applicableTo($this->ada, Ability::update()));
    }

    #[Test]
    public function it_lists_no_perimeter_for_a_reader_none_allows(): void
    {
        $control = ConfiguredPostControl::bind($this->clientPerimeter());

        $this->assertSame([], $control->applicableTo($this->ada, Ability::delete()));
    }

    #[Test]
    public function it_tells_which_applicable_perimeters_admit_a_row(): void
    {
        $shared = SharedPerimeter::new()
            ->allowed(fn (User $user): bool => true)
            ->should(fn (User $user, Post $post): bool => $post->sharedWith()->whereKey($user->getKey())->exists());
        $client = $this->clientPerimeter();
        $control = ConfiguredPostControl::bind($client, $shared);
        $this->roadmap->sharedWith()->attach($this->ada);

        $admitting = fn (Post $post): array => array_values(array_filter(
            $control->applicableTo($this->ada, Ability::view()),
            fn (Perimeter $perimeter): bool => $perimeter->admits($this->ada, Ability::view(), $post),
        ));

        $this->assertSame([$client], $admitting($this->releaseNotes));
        $this->assertSame([$shared], $admitting($this->roadmap));
    }
}

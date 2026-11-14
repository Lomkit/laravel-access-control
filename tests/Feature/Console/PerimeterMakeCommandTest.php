<?php

namespace Lomkit\Access\Tests\Feature\Console;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;
use Lomkit\Access\Ability;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class PerimeterMakeCommandTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->removeGeneratedFiles();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->removeGeneratedFiles();

        parent::tearDown();
    }

    protected function removeGeneratedFiles(): void
    {
        File::delete([
            app_path('Access/Perimeters/LedgerPerimeter.php'),
            app_path('Access/Perimeters/ClosurePerimeter.php'),
            app_path('Access/Perimeters/HookedPerimeter.php'),
        ]);
    }

    #[Test]
    public function it_generates_an_empty_perimeter_by_default(): void
    {
        $this->artisan('make:perimeter', ['name' => 'LedgerPerimeter'])->assertOk();

        $source = File::get(app_path('Access/Perimeters/LedgerPerimeter.php'));

        $this->assertStringContainsString('class LedgerPerimeter extends Perimeter {}', $source);
        $this->assertStringNotContainsString('AccessContext', $source);
    }

    #[Test]
    public function it_declares_the_callbacks_of_an_empty_generated_perimeter_in_the_control(): void
    {
        $acme = Client::factory()->createOne(['name' => 'Acme']);
        $ada = User::factory()->for($acme)->createOne(['name' => 'Ada', 'email' => 'ada@example.com']);
        Post::factory()->for(Project::factory()->for($acme))->for($ada, 'author')->createOne(['title' => 'Draft']);
        Post::factory()->for(Project::factory()->for(Client::factory()))->for($ada, 'author')->createOne(['title' => 'Roadmap']);
        $this->artisan('make:perimeter', ['name' => 'ClosurePerimeter'])->assertOk();
        require_once app_path('Access/Perimeters/ClosurePerimeter.php');

        ConfiguredPostControl::bind(
            ('App\\Access\\Perimeters\\ClosurePerimeter')::new()
                ->allowed(fn (User $user, Ability $ability): bool => $ability->is('view'))
                ->should(fn (User $user, Post $post): bool => $post->project->client()->is($user->client))
                ->query(function (Builder $query, User $user): void {
                    $query->whereHas('project', fn (Builder $projects) => $projects->whereBelongsTo($user->client));
                }),
        );

        $this->assertSame(['Draft'], Post::controlledFor($ada)->pluck('title')->all());
        $this->assertTrue($ada->can('view', Post::query()->where('title', 'Draft')->firstOrFail()));
        $this->assertFalse($ada->can('update', Post::query()->where('title', 'Draft')->firstOrFail()));
    }

    #[Test]
    public function it_generates_a_perimeter_that_overrides_the_hooks_with_the_hooks_option(): void
    {
        $this->artisan('make:perimeter', ['name' => 'HookedPerimeter', '--hooks' => true])->assertOk();

        $source = File::get(app_path('Access/Perimeters/HookedPerimeter.php'));

        $this->assertStringContainsString("use Lomkit\\Access\\AccessContext;\n", $source);
        $this->assertStringContainsString("protected function allows(AccessContext \$context): bool\n    {\n        return false;\n    }", $source);
        $this->assertStringContainsString("protected function includes(Model \$row, AccessContext \$context): bool\n    {\n        return false;\n    }", $source);
        $this->assertStringContainsString("protected function scope(Builder \$query, AccessContext \$context): void\n    {\n        \$query->whereRaw('0=1');\n    }", $source);
        $this->assertStringContainsString("protected function scoutScope(ScoutBuilder \$query, AccessContext \$context): void\n    {\n        \$query->where('__NOT_A_VALID_FIELD__', 0);\n    }", $source);

        require_once app_path('Access/Perimeters/HookedPerimeter.php');

        $perimeter = ('App\\Access\\Perimeters\\HookedPerimeter')::new();
        $query = Post::query();
        $perimeter->applyQueryCallback($query, AccessContext::of(new User, Ability::view()));

        $this->assertInstanceOf(Perimeter::class, $perimeter);
        $this->assertFalse($perimeter->admits(new User, Ability::view(), new Post));
        $this->assertSame([['type' => 'raw', 'sql' => '0=1', 'boolean' => 'and']], $query->getQuery()->wheres);
    }

    #[Test]
    public function it_generates_an_overlay_perimeter(): void
    {
        $this->artisan('make:perimeter', ['name' => 'LedgerPerimeter', '--overlay' => true])->assertOk();

        $this->assertStringContainsString('class LedgerPerimeter extends OverlayPerimeter', File::get(app_path('Access/Perimeters/LedgerPerimeter.php')));
    }

    #[Test]
    public function it_asks_whether_the_rules_go_in_hooks_when_no_option_is_given(): void
    {
        $this->artisan('make:perimeter')
            ->expectsQuestion('What should the perimeter be named?', 'LedgerPerimeter')
            ->expectsConfirmation('Should the rules be written in the perimeter class, with hooks?', 'yes')
            ->assertOk();

        $this->assertStringContainsString('protected function allows(AccessContext $context): bool', File::get(app_path('Access/Perimeters/LedgerPerimeter.php')));
    }

    #[Test]
    public function it_asks_whether_the_perimeter_overlays_when_no_option_is_given(): void
    {
        $this->artisan('make:perimeter')
            ->expectsQuestion('What should the perimeter be named?', 'LedgerPerimeter')
            ->expectsConfirmation('Should the rules be written in the perimeter class, with hooks?', 'no')
            ->expectsConfirmation('Should this perimeter be an overlay?', 'yes')
            ->assertOk();

        $this->assertStringContainsString('class LedgerPerimeter extends OverlayPerimeter', File::get(app_path('Access/Perimeters/LedgerPerimeter.php')));
    }
}

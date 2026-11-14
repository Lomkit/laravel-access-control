<?php

namespace Lomkit\Access\Tests\Feature\Perimeters;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Tests\Fixtures\Agencies\Agencies;
use Lomkit\Access\Tests\Fixtures\Agencies\Member;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\SharedPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Blog\Writer;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ReaderTypeTest extends PackageTestCase
{
    protected User $ada;

    protected Member $linus;

    protected Post $draft;

    protected Post $roadmap;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog, new Agencies];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $acme = Client::factory()->createOne(['name' => 'Acme']);
        $globex = Client::factory()->createOne(['name' => 'Globex']);
        $this->ada = User::factory()->for($acme)->createOne(['name' => 'Ada', 'email' => 'ada@example.com']);
        $hank = User::factory()->for($globex)->createOne(['name' => 'Hank', 'email' => 'hank@example.com']);
        $this->linus = Member::factory()->createOne(['name' => 'Linus', 'email' => 'linus@example.com']);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $warehouse = Project::factory()->for($globex)->createOne(['name' => 'Warehouse']);
        $this->draft = Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Draft']);
        $this->roadmap = Post::factory()->for($warehouse)->for($hank, 'author')->createOne(['title' => 'Roadmap']);
    }

    /** @return array<string, array{Closure, bool, bool}> */
    public static function readerTypes(): array
    {
        return [
            'a model class' => [fn (User $user): bool => true, true, false],
            'another model class' => [fn (Member $member): bool => true, false, true],
            'a nullable model class' => [fn (?User $user): bool => true, true, false],
            'a union of model classes' => [fn (User|Member $reader): bool => true, true, true],
            'an interface' => [fn (Writer $writer): bool => true, true, false],
            'an interface both readers implement' => [fn (Authenticatable $reader): bool => true, true, true],
            'an intersection' => [fn (User&Writer $writer): bool => true, true, false],
            'Model' => [fn (Model $reader): bool => true, true, true],
            'object' => [fn (object $reader): bool => true, true, true],
            'mixed' => [fn (mixed $reader): bool => true, true, true],
            'no type' => [fn ($reader): bool => true, true, true],
            'no parameter' => [fn (): bool => true, true, true],
        ];
    }

    #[Test]
    #[DataProvider('readerTypes')]
    public function it_applies_a_perimeter_only_to_the_readers_its_closures_accept(Closure $allowed, bool $appliesToUser, bool $appliesToMember): void
    {
        ConfiguredPostControl::bind(GlobalPerimeter::new()->allowed($allowed)->unrestricted());

        $this->assertSame($appliesToUser, Gate::forUser($this->ada)->allows('update', $this->roadmap));
        $this->assertSame($appliesToMember, Gate::forUser($this->linus)->allows('update', $this->roadmap));
        $this->assertSame($appliesToUser ? 2 : 0, Post::controlledFor($this->ada)->count());
        $this->assertSame($appliesToMember ? 2 : 0, Post::controlledFor($this->linus)->count());
    }

    #[Test]
    public function it_skips_a_perimeter_whose_query_closure_takes_another_reader(): void
    {
        ConfiguredPostControl::bind(
            ClientPerimeter::new()
                ->allowed(fn (Model $reader): bool => true)
                ->query(function (Builder $query, User $user): void {
                    $query->whereHas('project', fn (Builder $projects) => $projects->whereBelongsTo($user->client));
                }),
            SharedPerimeter::new()
                ->allowed(fn (Member $member): bool => true)
                ->query(function (Builder $query, Member $member): void {
                    $query->where('title', 'Roadmap');
                }),
        );

        $this->assertSame(['Draft'], Post::controlledFor($this->ada)->pluck('title')->all());
        $this->assertSame(['Roadmap'], Post::controlledFor($this->linus)->pluck('title')->all());
    }

    #[Test]
    public function it_skips_a_perimeter_whose_should_closure_takes_another_reader(): void
    {
        ConfiguredPostControl::bind(
            ClientPerimeter::new()
                ->allowed(fn ($reader): bool => true)
                ->should(fn (User $user, Post $post): bool => true)
                ->query(fn (Builder $query) => $query->whereRaw('1=1')),
        );

        $this->assertTrue(Gate::forUser($this->ada)->allows('update', $this->draft));
        $this->assertFalse(Gate::forUser($this->linus)->allows('update', $this->draft));
        $this->assertSame(0, Post::controlledFor($this->linus)->count());
    }

    #[Test]
    public function it_admits_no_row_for_a_reader_its_closures_do_not_accept(): void
    {
        $perimeter = ClientPerimeter::new()
            ->allowed(fn (User $user): bool => true)
            ->should(fn (User $user, Post $post): bool => true);

        $this->assertTrue($perimeter->admits($this->ada, Ability::update(), $this->draft));
        $this->assertFalse($perimeter->admits($this->linus, Ability::update(), $this->draft));
    }
}

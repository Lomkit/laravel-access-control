<?php

namespace Lomkit\Access\Tests\Feature\Perimeters;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\ImpurePerimetersException;
use Lomkit\Access\Exceptions\UnbuildablePerimeterException;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredArticleControl;
use Lomkit\Access\Tests\Fixtures\Constructed\AbstractShelfPerimeter;
use Lomkit\Access\Tests\Fixtures\Constructed\ConfiguredShelfArticleControl;
use Lomkit\Access\Tests\Fixtures\Constructed\DefaultShelfPerimeter;
use Lomkit\Access\Tests\Fixtures\Constructed\GuardedShelfPerimeter;
use Lomkit\Access\Tests\Fixtures\Constructed\Shelf;
use Lomkit\Access\Tests\Fixtures\Constructed\ShelfPerimeter;
use Lomkit\Access\Tests\Fixtures\Constructed\ShelvedArticlesPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\ArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class ConstructedPerimeterTest extends SearchTestCase
{
    protected User $ada;

    protected User $grace;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Access::registerPolicies();
        $this->ada = $this->user('Ada');
        $this->grace = $this->user('Grace');
        Article::withoutSyncingToSearch(function (): void {
            Article::factory()->createOne(['author_id' => $this->ada->getKey(), 'title' => 'Featured primer', 'featured' => true]);
            Article::factory()->createOne(['author_id' => $this->ada->getKey(), 'title' => 'Plain notes']);
            Article::factory()->createOne(['author_id' => $this->grace->getKey(), 'title' => 'Featured recipes', 'featured' => true]);
            Article::factory()->createOne(['author_id' => $this->grace->getKey(), 'title' => 'Plain internals']);
        });
    }

    protected function shelved(ShelvedArticlesPerimeter $perimeter, Shelf $shelf, string $keys): ShelvedArticlesPerimeter
    {
        return $perimeter->searchKeys(
            name: $keys,
            row: fn (Article $article): mixed => $article->featured === $shelf->holdsFeatured() ? $article->author_id : null,
            reader: fn (User $user): mixed => $user->getKey(),
        );
    }

    /** @return list<string> */
    protected function titles(User $reader, string $ability = 'view'): array
    {
        return Article::controlledFor($reader, $ability)
            ->orderBy('id')
            ->pluck('title')
            ->all();
    }

    #[Test]
    public function it_keeps_the_constructor_state_through_every_fluent_method(): void
    {
        $declared = ShelfPerimeter::reading(Shelf::Featured);
        $perimeter = $declared
            ->allowed(fn (User $user, Ability $ability): bool => $ability->is('view'))
            ->should(fn (User $user, Article $article): bool => $article->featured)
            ->query(fn (Builder $query): Builder => $query->where('featured', true))
            ->searchKeys(
                name: 'featured',
                row: fn (Article $article): ?string => $article->featured ? 'yes' : null,
                reader: fn (User $user): string => 'yes',
            )
            ->reindexWhen(User::class, ['name'], fn (User $user) => Article::query()->where('author_id', $user->getKey()));

        $this->assertInstanceOf(ShelfPerimeter::class, $perimeter);
        $this->assertNotSame($declared, $perimeter);
        $this->assertSame(Shelf::Featured, $perimeter->shelf);
        $this->assertFalse($perimeter->writing);
        $this->assertNotNull($perimeter->declaredSearchKeys());
        $this->assertNull($declared->declaredSearchKeys());
        $this->assertTrue($perimeter->admits($this->ada, Ability::view(), Article::query()->where('title', 'Featured primer')->firstOrFail()));
        $this->assertFalse($perimeter->admits($this->ada, Ability::update(), Article::query()->where('title', 'Featured primer')->firstOrFail()));
    }

    #[Test]
    public function it_keeps_the_constructor_state_of_an_unrestricted_perimeter(): void
    {
        $perimeter = ShelfPerimeter::writing(Shelf::Plain)
            ->allowed(fn (User $user): bool => true)
            ->unrestricted();

        $this->assertTrue($perimeter->isUnrestricted());
        $this->assertSame(Shelf::Plain, $perimeter->shelf);
        $this->assertTrue($perimeter->writing);
    }

    #[Test]
    public function it_compares_the_promoted_properties_of_a_perimeter_class(): void
    {
        $featured = ShelfPerimeter::reading(Shelf::Featured);

        $this->assertTrue($featured->shape()->matches(ShelfPerimeter::reading(Shelf::Featured)->shape()));
        $this->assertTrue($featured->shape()->matches((new ShelfPerimeter(Shelf::Featured, false))->shape()));
        $this->assertFalse($featured->shape()->matches(ShelfPerimeter::writing(Shelf::Featured)->shape()));
        $this->assertFalse($featured->shape()->matches(ShelfPerimeter::reading(Shelf::Plain)->shape()));
    }

    #[Test]
    public function it_agrees_on_every_decision_path_and_in_the_search_for_both_variants(): void
    {
        ConfiguredArticleControl::bind(
            $this->shelved(ShelvedArticlesPerimeter::reading(Shelf::Featured), Shelf::Featured, 'featured_author'),
            $this->shelved(ShelvedArticlesPerimeter::writing(Shelf::Plain), Shelf::Plain, 'plain_author'),
        );
        $this->reindex();

        $this->assertSame(['Featured primer', 'Plain notes'], $this->titles($this->ada));
        $this->assertSame(['Plain notes'], $this->titles($this->ada, 'update'));
        $this->assertSame(['Featured recipes', 'Plain internals'], $this->titles($this->grace));

        foreach ([$this->ada, $this->grace] as $reader) {
            $this->assertControlAgrees(ArticleControl::class, $reader, Article::query()->orderBy('id')->get(), ['view', 'update']);
        }

        $this->assertSearchAgrees(ArticleControl::class, [$this->ada, $this->grace]);
    }

    #[Test]
    public function it_catches_a_constructor_argument_read_from_a_value_that_changes_between_two_builds(): void
    {
        config(['articles.featured' => true]);
        $this->app->instance(ArticleControl::class, new ConfiguredShelfArticleControl);

        $this->assertSame(['Featured primer'], $this->titles($this->ada));

        config(['articles.featured' => false]);

        $this->expectException(ImpurePerimetersException::class);

        Access::controlFor(Article::class);
    }

    #[Test]
    public function it_passes_constructor_arguments_built_from_the_same_values_twice(): void
    {
        config(['articles.featured' => false]);
        $this->app->instance(ArticleControl::class, new ConfiguredShelfArticleControl);

        $this->assertSame(['Plain notes'], $this->titles($this->ada));
        $this->assertSame(['Plain notes'], $this->titles($this->ada));

        Access::control(ArticleControl::class)->verifyPerimeters();
    }

    #[Test]
    public function it_refuses_new_on_a_class_whose_constructor_requires_arguments(): void
    {
        $this->expectException(UnbuildablePerimeterException::class);
        $this->expectExceptionMessage(sprintf('The constructor of the [%1$s] perimeter requires the [shelf, writing] arguments, so %1$s::new() cannot build it.', ShelfPerimeter::class));

        ShelfPerimeter::new();
    }

    #[Test]
    public function it_refuses_new_on_a_class_whose_constructor_is_not_public(): void
    {
        $this->expectException(UnbuildablePerimeterException::class);
        $this->expectExceptionMessage(sprintf('The constructor of the [%1$s] perimeter is not public, so %1$s::new() cannot build it.', ShelvedArticlesPerimeter::class));

        ShelvedArticlesPerimeter::new();
    }

    #[Test]
    public function it_refuses_new_on_a_class_whose_constructor_is_protected(): void
    {
        $this->expectException(UnbuildablePerimeterException::class);
        $this->expectExceptionMessage(sprintf('The constructor of the [%1$s] perimeter is not public, so %1$s::new() cannot build it.', GuardedShelfPerimeter::class));

        GuardedShelfPerimeter::new();
    }

    #[Test]
    public function it_refuses_new_on_an_abstract_class(): void
    {
        $this->expectException(UnbuildablePerimeterException::class);
        $this->expectExceptionMessage(sprintf('The [%1$s] perimeter is abstract, so %1$s::new() cannot build it.', AbstractShelfPerimeter::class));

        AbstractShelfPerimeter::new();
    }

    #[Test]
    public function it_builds_with_new_a_class_whose_constructor_arguments_are_optional(): void
    {
        $perimeter = DefaultShelfPerimeter::new();

        $this->assertInstanceOf(DefaultShelfPerimeter::class, $perimeter);
        $this->assertSame(Shelf::Featured, $perimeter->shelf);
    }
}

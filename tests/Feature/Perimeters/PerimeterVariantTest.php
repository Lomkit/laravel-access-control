<?php

namespace Lomkit\Access\Tests\Feature\Perimeters;

use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\DuplicateSearchKeyNameException;
use Lomkit\Access\Exceptions\ImpurePerimetersException;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredArticleControl;
use Lomkit\Access\Tests\Fixtures\ConfiguredQuietArticleControl;
use Lomkit\Access\Tests\Fixtures\ConfiguredVariantArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\ArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\QuietArticle;
use Lomkit\Access\Tests\Fixtures\Search\QuietArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use Lomkit\Access\Tests\Fixtures\Search\WrittenArticlesPerimeter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class PerimeterVariantTest extends SearchTestCase
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
        Gate::define('view written articles', fn (User $user): bool => true);
        Gate::define('update written articles', fn (User $user): bool => $user->is($this->ada));
        Gate::define('create written articles', fn (User $user): bool => true);
    }

    protected function written(bool $featured, string $keys): WrittenArticlesPerimeter
    {
        return WrittenArticlesPerimeter::new()
            ->featured($featured)
            ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} written articles"))
            ->searchKeys(
                name: $keys,
                row: fn (Article $article): mixed => $article->featured === $featured ? $article->author_id : null,
                reader: fn (User $user): mixed => $user->getKey(),
            );
    }

    /** @return list<string> */
    protected function titles(string $model, User $reader): array
    {
        return $model::controlledFor($reader)
            ->orderBy('id')
            ->pluck('title')
            ->all();
    }

    #[Test]
    public function it_keeps_each_variant_of_one_class_to_its_own_rows_in_two_controls(): void
    {
        ConfiguredArticleControl::bind($this->written(true, 'featured_author'));
        ConfiguredQuietArticleControl::bind($this->written(false, 'plain_author'));

        $this->assertSame(['Featured primer'], $this->titles(Article::class, $this->ada));
        $this->assertSame(['Plain notes'], $this->titles(QuietArticle::class, $this->ada));
    }

    #[Test]
    public function it_agrees_on_every_decision_path_and_in_the_search_for_each_variant(): void
    {
        ConfiguredArticleControl::bind($this->written(true, 'featured_author'));
        ConfiguredQuietArticleControl::bind($this->written(false, 'plain_author'));
        $this->reindex();

        foreach ([ArticleControl::class => Article::class, QuietArticleControl::class => QuietArticle::class] as $control => $model) {
            foreach ([$this->ada, $this->grace] as $reader) {
                $this->assertControlAgrees($control, $reader, $model::query()->orderBy('id')->get(), ['view', 'update']);
            }

            $this->assertSearchAgrees($control, [$this->ada, $this->grace]);
        }

        $this->assertSame(['Featured primer'], Article::search('')->controlledFor($this->ada)->get()->pluck('title')->all());
    }

    #[Test]
    public function it_adds_up_two_variants_of_one_class_with_distinct_key_names(): void
    {
        ConfiguredArticleControl::bind($this->written(true, 'featured_author'), $this->written(false, 'plain_author'));
        $this->reindex();

        $this->assertSame(['Featured primer', 'Plain notes'], $this->titles(Article::class, $this->ada));
        $this->assertSearchAgrees(ArticleControl::class, [$this->ada, $this->grace]);
    }

    #[Test]
    public function it_refuses_two_variants_of_one_class_under_the_same_key_name(): void
    {
        ConfiguredArticleControl::bind($this->written(true, 'author'), $this->written(false, 'author'));

        $this->expectException(DuplicateSearchKeyNameException::class);

        Article::controlledFor($this->ada)->get();
    }

    #[Test]
    public function it_refuses_a_draft_of_another_variant(): void
    {
        ConfiguredArticleControl::bind($this->written(true, 'featured_author'));

        $featured = new Article(['author_id' => $this->ada->getKey(), 'title' => 'New', 'featured' => true]);
        $plain = new Article(['author_id' => $this->ada->getKey(), 'title' => 'New', 'featured' => false]);

        $this->assertTrue(Gate::forUser($this->ada)->allows('create', [Article::class, $featured]));
        $this->assertSame('outside_rows', Gate::forUser($this->ada)->inspect('create', [Article::class, $plain])->code());
    }

    #[Test]
    public function it_catches_a_variant_read_from_a_value_that_changes_between_two_builds(): void
    {
        config(['articles.featured' => true]);
        $this->app->instance(ArticleControl::class, new ConfiguredVariantArticleControl);

        $this->assertSame(['Featured primer'], $this->titles(Article::class, $this->ada));

        config(['articles.featured' => false]);

        $this->expectException(ImpurePerimetersException::class);

        Access::controlFor(Article::class);
    }

    #[Test]
    public function it_passes_a_variant_built_from_the_same_value_twice(): void
    {
        config(['articles.featured' => true]);
        $this->app->instance(ArticleControl::class, new ConfiguredVariantArticleControl);

        $this->assertSame(['Featured primer'], $this->titles(Article::class, $this->ada));
        $this->assertSame(['Featured primer'], $this->titles(Article::class, $this->ada));

        Access::control(ArticleControl::class)->verifyPerimeters();
    }
}

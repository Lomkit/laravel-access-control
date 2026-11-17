<?php

namespace Lomkit\Access\Tests\Feature\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\ArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\AuthorPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\BrandArticlesPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class SeveralBrandsSearchTest extends SearchTestCase
{
    private const array RIGHTS = [
        'Ada' => ['view brand articles' => [1], 'view own articles' => [1, 2], 'update own articles' => [1, 2]],
        'Grace' => ['view brand articles' => [2]],
    ];

    protected User $ada;

    protected User $grace;

    protected User $linus;

    protected int $brand = 1;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->ada = $this->user('Ada');
        $this->grace = $this->user('Grace');
        $this->linus = $this->user('Linus');
        Article::withoutSyncingToSearch(function (): void {
            Article::factory()->createOne(['author_id' => $this->ada->getKey(), 'brand_id' => 1, 'title' => 'Scout primer']);
            Article::factory()->createOne(['author_id' => $this->grace->getKey(), 'brand_id' => 1, 'title' => 'Scout internals']);
            Article::factory()->createOne(['author_id' => $this->ada->getKey(), 'brand_id' => 2, 'title' => 'Scout recipes']);
            Article::factory()->createOne(['author_id' => $this->grace->getKey(), 'brand_id' => 2, 'title' => 'Scout tuning']);
            Article::factory()->createOne(['author_id' => $this->ada->getKey(), 'brand_id' => 3, 'title' => 'Scout archive']);
        });
        Access::decisionContextUsing(fn (): array => ['brand' => $this->brand]);
        Access::registerPolicies();
        ConfiguredArticleControl::bind($this->brandArticles(), $this->ownArticles());
        $this->reindex();
    }

    #[Test]
    public function it_finds_every_row_of_one_brand_and_the_own_rows_of_another_in_one_search(): void
    {
        $this->actingAs($this->ada);

        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada, $this->grace, $this->linus]);
        $this->assertSame(['Scout internals', 'Scout primer', 'Scout recipes'], $this->searchedTitles());
        $this->assertSame(['Scout recipes', 'Scout tuning'], $this->searchedTitlesFor($this->grace));
        $this->assertSame([], $this->searchedTitlesFor($this->linus));
    }

    #[Test]
    public function it_finds_the_same_rows_whatever_the_current_brand(): void
    {
        $this->actingAs($this->ada);

        foreach ([1, 2, 3] as $brand) {
            $this->brand = $brand;

            $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada, $this->grace]);
            $this->assertSame(['Scout internals', 'Scout primer', 'Scout recipes'], $this->searchedTitles());
        }
    }

    #[Test]
    public function it_decides_each_row_of_several_brands_as_the_search_finds_it(): void
    {
        $articles = Article::query()->orderBy('id')->get();
        $found = Article::search('Scout')
            ->controlledFor($this->ada)
            ->get()
            ->modelKeys();

        $this->brand = 2;

        $this->assertControlAgrees(ArticleControl::class, $this->ada, $articles, ['view', 'update']);
        $this->assertControlAgrees(ArticleControl::class, $this->grace, $articles, ['view', 'update']);
        $this->assertEqualsCanonicalizing(
            $found,
            $articles->filter(fn (Article $article): bool => Gate::forUser($this->ada)->allows('view', $article))->modelKeys(),
        );
        $this->assertSame(
            ['Scout primer', 'Scout recipes'],
            $articles->filter(fn (Article $article): bool => Gate::forUser($this->ada)->allows('update', $article))->pluck('title')->values()->all(),
        );
    }

    private function brandArticles(): Perimeter
    {
        return BrandArticlesPerimeter::new()
            ->allowed(fn (User $user, Ability $ability): bool => self::brandsWhere($user, "{$ability} brand articles") !== [])
            ->should(fn (User $user, Article $article, Ability $ability): bool => in_array($article->brand_id, self::brandsWhere($user, "{$ability} brand articles"), true))
            ->query(function (Builder $query, User $user, Ability $ability): void {
                $query->whereIn('brand_id', self::brandsWhere($user, "{$ability} brand articles"));
            })
            ->searchKeys(
                name: 'brand',
                row: fn (Article $article): mixed => $article->brand_id,
                reader: fn (User $user): array => self::brandsWhere($user, 'view brand articles'),
            );
    }

    private function ownArticles(): Perimeter
    {
        return AuthorPerimeter::new()
            ->allowed(fn (User $user, Ability $ability): bool => self::brandsWhere($user, "{$ability} own articles") !== [])
            ->should(fn (User $user, Article $article, Ability $ability): bool => $article->author_id === $user->getKey() && in_array($article->brand_id, self::brandsWhere($user, "{$ability} own articles"), true))
            ->query(function (Builder $query, User $user, Ability $ability): void {
                $query->where('author_id', $user->getKey())->whereIn('brand_id', self::brandsWhere($user, "{$ability} own articles"));
            })
            ->searchKeys(
                name: 'own',
                row: fn (Article $article): array => [[$article->brand_id, $article->author_id]],
                reader: fn (User $user): array => array_map(fn (int $brand): array => [$brand, $user->getKey()], self::brandsWhere($user, 'view own articles')),
            );
    }

    /** @return list<int> */
    private static function brandsWhere(User $user, string $permission): array
    {
        return self::RIGHTS[$user->name][$permission] ?? [];
    }

    /** @return list<string> */
    private function searchedTitlesFor(User $reader): array
    {
        return Article::search('Scout')
            ->controlledFor($reader)
            ->get()
            ->pluck('title')
            ->sort()
            ->values()
            ->all();
    }
}

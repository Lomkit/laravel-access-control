<?php

namespace Lomkit\Access\Tests\Feature\Search;

use Lomkit\Access\Access;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\ArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\AuthorPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\BrandPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\BrandSearchBoundary;
use Lomkit\Access\Tests\Fixtures\Search\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class ReaderContextKeysTest extends SearchTestCase
{
    protected User $ada;

    protected User $grace;

    protected int $brand = 1;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->ada = $this->user('Ada');
        $this->grace = $this->user('Grace');
        Article::withoutSyncingToSearch(function (): void {
            Article::factory()->createOne(['author_id' => $this->ada->getKey(), 'brand_id' => 1, 'title' => 'Scout primer']);
            Article::factory()->createOne(['author_id' => $this->ada->getKey(), 'brand_id' => 2, 'title' => 'Scout recipes']);
            Article::factory()->createOne(['author_id' => $this->grace->getKey(), 'brand_id' => 1, 'title' => 'Scout internals']);
            Article::factory()->createOne(['author_id' => $this->grace->getKey(), 'brand_id' => 2, 'title' => 'Scout tuning']);
        });
        Access::decisionContextUsing(fn (): array => ['brand' => $this->brand]);
        $this->actingAs($this->ada);
    }

    protected function brandAuthor(): BrandPerimeter
    {
        return BrandPerimeter::new()
            ->allowed(fn (User $user): bool => true)
            ->searchKeys(
                name: 'brand_author',
                row: fn (Article $article): array => [[$article->brand_id, $article->author_id]],
                reader: fn (User $user, AccessContext $context): array => [[$context->decisionContext()['brand'] ?? 0, $user->getKey()]],
            );
    }

    protected function unrestricted(): Levels
    {
        return Levels::of(
            GlobalPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->unrestricted(),
        );
    }

    #[Test]
    public function it_gives_the_decision_context_to_the_reader_keys_of_a_perimeter(): void
    {
        ConfiguredArticleControl::bind($this->brandAuthor());
        $this->reindex();

        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada, $this->grace]);
        $this->assertSame(['Scout primer'], $this->searchedTitles());

        $this->brand = 2;

        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada, $this->grace]);
        $this->assertSame(['Scout recipes'], $this->searchedTitles());
    }

    #[Test]
    public function it_gives_the_decision_context_to_the_reader_keys_of_a_boundary_class(): void
    {
        ConfiguredArticleControl::bindWithin(new BrandSearchBoundary, $this->unrestricted());
        $this->reindex();

        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada, $this->grace]);
        $this->assertSame(['Scout internals', 'Scout primer'], $this->searchedTitles());

        $this->brand = 2;

        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada, $this->grace]);
        $this->assertSame(['Scout recipes', 'Scout tuning'], $this->searchedTitles());
    }

    #[Test]
    public function it_gives_the_view_ability_to_the_reader_closure(): void
    {
        $abilities = [];
        ConfiguredArticleControl::bind(
            AuthorPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys(
                    name: 'author',
                    row: fn (Article $article): mixed => $article->author_id,
                    reader: function (User $user, AccessContext $context) use (&$abilities): mixed {
                        $abilities[] = (string) $context->ability();

                        return $user->getKey();
                    },
                ),
        );
        $this->reindex();

        $this->assertSame(['Scout primer', 'Scout recipes'], $this->searchedTitles());
        $this->assertNotSame([], $abilities);
        $this->assertSame(['view'], array_values(array_unique($abilities)));
    }

    #[Test]
    public function it_calls_a_reader_closure_of_one_parameter_with_the_reader_alone(): void
    {
        $arguments = [];
        ConfiguredArticleControl::bind(
            AuthorPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys(
                    name: 'author',
                    row: fn (Article $article): mixed => $article->author_id,
                    reader: function (User $user) use (&$arguments): mixed {
                        $arguments[] = func_num_args();

                        return $user->getKey();
                    },
                ),
        );
        $this->reindex();

        $this->assertSame(['Scout primer', 'Scout recipes'], $this->searchedTitles());
        $this->assertNotSame([], $arguments);
        $this->assertSame([1], array_values(array_unique($arguments)));
    }

    #[Test]
    public function it_calls_the_reader_value_of_a_column_boundary_with_the_reader_alone(): void
    {
        $arguments = [];
        ConfiguredArticleControl::bindWithin(
            Boundary::where('brand_id', function (User $user) use (&$arguments): int {
                $arguments[] = func_num_args();

                return 2;
            }),
            $this->unrestricted(),
        );
        $this->reindex();

        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada]);
        $this->assertSame(['Scout recipes', 'Scout tuning'], $this->searchedTitles());
        $this->assertSame([1], array_values(array_unique($arguments)));
    }
}

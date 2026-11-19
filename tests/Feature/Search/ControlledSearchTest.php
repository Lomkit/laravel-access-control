<?php

namespace Lomkit\Access\Tests\Feature\Search;

use Illuminate\Support\Facades\Auth;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\ConflictingSearchScopeException;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Search\SearchKeyDigest;
use Lomkit\Access\Tests\Fixtures\Agencies\Member;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\ArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\AuthorPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\FeaturedPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use Lomkit\Access\Tests\Fixtures\Search\SharedPerimeter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class ControlledSearchTest extends SearchTestCase
{
    protected User $ada;

    protected User $grace;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->ada = $this->user('Ada');
        $this->grace = $this->user('Grace');
        Article::factory()->createOne(['author_id' => $this->ada->getKey(), 'title' => 'Scout primer']);
        Article::factory()->createOne(['author_id' => $this->grace->getKey(), 'title' => 'Scout recipes', 'featured' => true]);
        Article::factory()->createOne(['author_id' => $this->grace->getKey(), 'title' => 'Scout internals']);
        $this->actingAs($this->ada);
    }

    /** @return array<string, list<string>> */
    protected function filterOf(string $query = 'Scout'): array
    {
        $search = Article::search($query)->controlled();
        $search->get();

        return $search->whereIns;
    }

    #[Test]
    public function it_searches_the_rows_whose_keys_meet_the_keys_of_the_reader(): void
    {
        $this->allow(['view own articles', 'view featured articles']);

        $this->assertSame(['Scout primer', 'Scout recipes'], $this->searchedTitles());
        $this->assertSame([
            '_access' => SearchKeyDigest::all(["author.{$this->ada->getKey()}", 'featured.yes']),
        ], $this->filterOf());
    }

    #[Test]
    public function it_searches_with_the_keys_of_the_reader_given_to_controlled_for(): void
    {
        $this->allow(['view own articles']);

        $titles = Article::search('Scout')
            ->controlledFor($this->grace)
            ->get()
            ->pluck('title')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['Scout internals', 'Scout recipes'], $titles);
    }

    #[Test]
    public function it_returns_an_empty_page_without_calling_the_engine_when_controlled_for_no_reader(): void
    {
        $this->allow(['view global articles']);

        $this->assertCount(0, Article::search('Scout')->controlledFor(null)->get());
        $this->assertSame([], $this->engine->searches);
    }

    #[Test]
    public function it_refuses_a_search_controlled_for_a_reader_and_uncontrolled(): void
    {
        $this->expectException(ConflictingSearchScopeException::class);

        Article::search('Scout')->controlledFor($this->grace)->uncontrolled();
    }

    #[Test]
    public function it_gives_only_the_keys_of_the_highest_level_the_reader_reaches(): void
    {
        ConfiguredArticleControl::bind(
            Levels::of(
                ClientPerimeter::new()
                    ->allowed(fn (User $user): bool => true)
                    ->searchKeys('client', fn (Article $article): int => 1, fn (User $user): mixed => $user->client_id),
                AuthorPerimeter::new()
                    ->allowed(fn (User $user): bool => true)
                    ->searchKeys('author', fn (Article $article): mixed => $article->author_id, fn (User $user): mixed => $user->getKey()),
            ),
        );
        $this->reindex();

        $this->assertSame(SearchKeyDigest::all(["client.{$this->ada->client_id}"]), $this->filterOf()['_access']);
    }

    #[Test]
    public function it_drops_the_key_filter_for_an_unrestricted_level(): void
    {
        $this->allow(['view global articles', 'view featured articles']);

        $this->assertSame(['Scout internals', 'Scout primer', 'Scout recipes'], $this->searchedTitles());
        $this->assertSame([], $this->filterOf());
    }

    #[Test]
    public function it_returns_an_empty_page_without_calling_the_engine_when_no_perimeter_allows_the_reader(): void
    {
        $search = Article::search('Scout')->controlled();

        $this->assertCount(0, $search->get());
        $this->assertSame(0, $search->paginate(10)->total());
        $this->assertSame([], $this->engine->searches);
    }

    #[Test]
    public function it_returns_an_empty_page_without_calling_the_engine_when_the_reader_has_no_key(): void
    {
        ConfiguredArticleControl::bind(
            SharedPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys('shared', fn (Article $article): int => 1, fn (User $user): array => []),
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys('client', fn (Article $article): int => 1, fn (User $user): ?int => null),
        );
        $this->reindex();

        $this->assertSame([], $this->searchedTitles());
        $this->assertSame([], $this->engine->searches);
    }

    #[Test]
    public function it_returns_an_empty_page_without_calling_the_engine_without_a_signed_in_user(): void
    {
        $this->allow(['view global articles']);
        Auth::forgetGuards();

        $this->assertSame([], $this->searchedTitles());
        $this->assertSame([], $this->engine->searches);
    }

    #[Test]
    public function it_searches_nothing_through_a_reached_perimeter_that_declares_no_search_keys(): void
    {
        ConfiguredArticleControl::bind(
            AuthorPerimeter::new()->allowed(fn (User $user): bool => true),
            FeaturedPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys('featured', fn (Article $article): ?string => $article->featured ? 'yes' : null, fn (User $user): string => 'yes'),
        );
        $this->reindex();

        $this->assertSame(['Scout recipes'], $this->searchedTitles());
    }

    #[Test]
    public function it_skips_a_perimeter_whose_reader_keys_are_typed_for_another_reader(): void
    {
        ConfiguredArticleControl::bind(
            AuthorPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys('author', fn (Article $article): mixed => $article->author_id, fn (Member $member): mixed => $member->getKey()),
        );
        $this->reindex();

        $this->assertSame([], $this->searchedTitles());
        $this->assertTrue(Access::control(ArticleControl::class)->searchFilter($this->ada)->isClosed());
    }

    #[Test]
    public function it_keeps_the_filter_when_the_search_adds_its_own_constraints(): void
    {
        $this->allow(['view own articles']);

        $titles = Article::search('Scout')
            ->controlled()
            ->whereIn('_access', ['featured.yes'])
            ->get()
            ->pluck('title')
            ->all();

        $this->assertSame(['Scout primer'], $titles);
    }
}

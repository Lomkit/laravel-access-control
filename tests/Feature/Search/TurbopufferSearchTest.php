<?php

namespace Lomkit\Access\Tests\Feature\Search;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Scout\Engines\TurbopufferEngine;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\AuthorPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class TurbopufferSearchTest extends SearchTestCase
{
    protected User $ada;

    #[\Override]
    protected function setUp(): void
    {
        if (! class_exists(TurbopufferEngine::class)) {
            $this->markTestSkipped('This Scout version has no Turbopuffer engine.');
        }

        parent::setUp();

        $this->ada = $this->user('Ada');
        Http::fake(['*' => Http::response(['rows' => [], 'aggregations' => ['count' => 0]])]);
        config([
            'scout.driver' => 'turbopuffer',
            'scout.turbopuffer.api_key' => 'test-key',
            'scout.turbopuffer.base_url' => 'https://turbopuffer.test',
            'scout.turbopuffer.retries' => 0,
            'scout.turbopuffer.model-settings' => [
                Article::class => [
                    'searchable-attributes' => ['title'],
                    'embedding' => ['attribute' => 'summary', 'driver' => 'turbopuffer'],
                    'schema' => [
                        'summary' => ['type' => 'string', 'embed' => 'text-embedding'],
                        '_access' => ['type' => '[]string', 'filterable' => true],
                        '_boundary' => ['type' => '[]string', 'filterable' => true],
                    ],
                ],
            ],
        ]);
    }

    /** @return list<array<array-key, mixed>> */
    protected function queries(): array
    {
        return array_values(array_map(
            fn (array $recorded): array => $recorded[0]->data(),
            array_filter(Http::recorded()->all(), fn (array $recorded): bool => str_ends_with($recorded[0]->url(), '/query')),
        ));
    }

    protected function filtersOfTheOnlyQuery(): mixed
    {
        $queries = $this->queries();

        $this->assertCount(1, $queries);

        return $queries[0]['filters'] ?? null;
    }

    /** @return list<mixed> */
    protected function adaAccess(): array
    {
        return ['_access', 'ContainsAny', ["author.{$this->ada->getKey()}"]];
    }

    #[Test]
    public function it_filters_the_access_keys_with_contains_any(): void
    {
        $this->allow(['view own articles']);

        Article::search('Scout')->controlledFor($this->ada)->get();

        $this->assertSame($this->adaAccess(), $this->filtersOfTheOnlyQuery());
    }

    #[Test]
    public function it_lists_every_key_of_the_reader_in_one_contains_any(): void
    {
        $this->allow(['view own articles', 'view featured articles']);

        Article::search('Scout')->controlledFor($this->ada)->get();

        $this->assertSame(['_access', 'ContainsAny', ["author.{$this->ada->getKey()}", 'featured.yes']], $this->filtersOfTheOnlyQuery());
    }

    #[Test]
    public function it_sends_no_filter_for_an_unrestricted_reader_without_a_boundary(): void
    {
        $this->allow(['view global articles']);

        Article::search('Scout')->controlledFor($this->ada)->get();

        $queries = $this->queries();
        $this->assertCount(1, $queries);
        $this->assertArrayNotHasKey('filters', $queries[0]);
    }

    #[Test]
    public function it_keeps_the_documents_inside_the_boundary_and_the_access_keys(): void
    {
        ConfiguredArticleControl::bindWithin(
            Boundary::where('workspace_id', fn (User $user): ?int => $user->current_workspace_id),
            AuthorPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys('author', fn (Article $article): mixed => $article->author_id, fn (User $user): mixed => $user->getKey()),
        );

        Article::search('Scout')->controlledFor($this->ada)->get();

        $this->assertSame(['And', [
            ['_boundary', 'ContainsAny', ['workspace_id.1']],
            $this->adaAccess(),
        ]], $this->filtersOfTheOnlyQuery());
    }

    #[Test]
    public function it_keeps_only_the_boundary_for_an_unrestricted_reader_inside_a_boundary(): void
    {
        ConfiguredArticleControl::bindWithin(
            Boundary::where('workspace_id', fn (User $user): ?int => $user->current_workspace_id),
            AuthorPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->unrestricted(),
        );

        Article::search('Scout')->controlledFor($this->ada)->get();

        $this->assertSame(['_boundary', 'ContainsAny', ['workspace_id.1']], $this->filtersOfTheOnlyQuery());
    }

    #[Test]
    public function it_returns_an_empty_page_without_calling_the_engine_for_a_closed_filter(): void
    {
        $search = Article::search('Scout')->controlledFor($this->ada);

        $this->assertCount(0, $search->get());
        $this->assertCount(0, $search->keys());
        $this->assertSame(0, $search->paginate(10)->total());
        $this->assertFalse($search->simplePaginate(10)->hasMorePages());
        $this->assertCount(0, Article::search('Scout')->controlledFor(null)->get());
        Http::assertNothingSent();
    }

    #[Test]
    public function it_ands_the_native_filters_passed_before_controlled(): void
    {
        $this->allow(['view own articles']);

        Article::search('Scout')
            ->options(['filters' => ['title', 'Eq', 'Scout primer']])
            ->controlledFor($this->ada)
            ->get();

        $this->assertSame(['And', [['title', 'Eq', 'Scout primer'], $this->adaAccess()]], $this->filtersOfTheOnlyQuery());
    }

    #[Test]
    public function it_ands_the_native_filters_passed_after_controlled(): void
    {
        $this->allow(['view own articles']);

        Article::search('Scout')
            ->controlledFor($this->ada)
            ->options(['filters' => ['Or', [['title', 'Eq', 'Scout primer'], ['featured', 'Eq', true]]]])
            ->get();

        $this->assertSame(['And', [
            ['Or', [['title', 'Eq', 'Scout primer'], ['featured', 'Eq', true]]],
            $this->adaAccess(),
        ]], $this->filtersOfTheOnlyQuery());
    }

    #[Test]
    public function it_puts_the_filter_back_when_the_app_drops_the_options_after_controlled(): void
    {
        $this->allow(['view own articles']);

        Article::search('Scout')
            ->controlledFor($this->ada)
            ->options(['consistency' => ['level' => 'eventual']])
            ->get();

        $this->assertSame($this->adaAccess(), $this->filtersOfTheOnlyQuery());
    }

    #[Test]
    public function it_keeps_the_filter_when_the_app_adds_a_where_in_on_the_access_keys(): void
    {
        $this->allow(['view own articles']);

        Article::search('Scout')
            ->controlledFor($this->ada)
            ->whereIn('_access', ['featured.yes'])
            ->get();

        $this->assertSame(['And', [$this->adaAccess(), ['_access', 'In', ['featured.yes']]]], $this->filtersOfTheOnlyQuery());
    }

    #[Test]
    public function it_applies_the_filter_once_however_often_the_search_runs(): void
    {
        $this->allow(['view own articles']);
        $search = Article::search('Scout')
            ->options(['filters' => ['title', 'Eq', 'Scout primer']])
            ->controlledFor($this->ada);

        $search->get();
        $search->get();

        $queries = $this->queries();
        $this->assertCount(2, $queries);
        $this->assertSame(['And', [['title', 'Eq', 'Scout primer'], $this->adaAccess()]], $queries[1]['filters']);
        $this->assertSame($queries[0]['filters'], $queries[1]['filters']);
    }

    #[Test]
    public function it_filters_the_count_of_a_paginated_search(): void
    {
        $this->allow(['view own articles']);

        Article::search('Scout')->controlledFor($this->ada)->paginate(10);

        $queries = $this->queries();
        $this->assertCount(2, $queries);
        $this->assertSame($this->adaAccess(), $queries[0]['filters']);
        $this->assertSame([['count' => ['Count']], $this->adaAccess()], [$queries[1]['aggregate_by'], $queries[1]['filters']]);
    }

    #[Test]
    public function it_filters_both_queries_of_a_hybrid_search(): void
    {
        $this->allow(['view own articles']);

        Article::search('Scout')->hybrid()->controlledFor($this->ada)->get();

        $queries = $this->queries();
        $this->assertCount(1, $queries);
        $this->assertSame($this->adaAccess(), $queries[0]['queries'][0]['filters']);
        $this->assertSame($this->adaAccess(), $queries[0]['queries'][1]['filters']);
    }

    #[Test]
    public function it_indexes_the_access_keys_as_string_arrays_with_the_schema_of_the_model(): void
    {
        $article = Article::factory()->createOne(['author_id' => $this->ada->getKey(), 'title' => 'Scout primer']);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && ! str_ends_with($request->url(), '/query')
            && $request['upsert_rows'][0]['_access'] === ["author.{$this->ada->getKey()}"]
            && $request['upsert_rows'][0]['id'] === $article->getKey()
            && $request['schema']['_access'] === ['type' => '[]string', 'filterable' => true]);
    }
}

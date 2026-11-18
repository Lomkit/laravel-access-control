<?php

namespace Lomkit\Access\Tests\Feature\Search;

use Laravel\Scout\EngineManager;
use Lomkit\Access\Access;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Exceptions\UncombinableSearchFilterException;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\AuthorPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use Lomkit\Access\Tests\Fixtures\Search\TypesenseRequestEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class TypesenseFilterByTest extends SearchTestCase
{
    protected User $ada;

    protected TypesenseRequestEngine $typesense;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $typesense = $this->typesense = new TypesenseRequestEngine;
        $this->app->make(EngineManager::class)->extend('typesense-requests', fn (): TypesenseRequestEngine => $typesense);
        config(['scout.driver' => 'typesense-requests']);
        $this->ada = $this->user('Ada');
        $this->allow(['view own articles']);
    }

    /** @param  array<string, mixed>  $parameters */
    protected function typesenseSearchParameters(array $parameters): void
    {
        config(['testing.article.typesense-search-parameters' => $parameters]);
    }

    protected function accessFilter(?User $reader = null, bool $quoted = true): string
    {
        $clauses = [];

        foreach (Access::controlFor(Article::class)->searchFilter($reader ?? $this->ada)->whereIns() as $field => $values) {
            $clauses[] = sprintf('%s:=[%s]', $field, implode(', ', array_map(fn (string $value): string => $quoted ? "`{$value}`" : $value, $values)));
        }

        return implode(' && ', $clauses);
    }

    protected function combined(string $app, ?User $reader = null): string
    {
        return "({$app}) && ({$this->accessFilter($reader)})";
    }

    /** @return array<string, mixed> */
    protected function onlyRequest(): array
    {
        $this->assertCount(1, $this->typesense->requests);

        return $this->typesense->requests[0];
    }

    #[Test]
    public function it_sends_the_access_filter_of_scout_when_the_app_passes_no_filter_by(): void
    {
        Article::search('Scout')->controlledFor($this->ada)->get();

        $request = $this->onlyRequest();
        $this->assertSame($this->accessFilter(quoted: false), $request['filter_by']);
        $this->assertTrue($request['filter_curated_hits']);
    }

    #[Test]
    public function it_ands_the_filter_by_passed_before_controlled(): void
    {
        Article::search('Scout')
            ->options(['filter_by' => 'featured:=true'])
            ->controlledFor($this->ada)
            ->get();

        $this->assertSame($this->combined('featured:=true'), $this->onlyRequest()['filter_by']);
    }

    #[Test]
    public function it_ands_the_filter_by_passed_after_controlled(): void
    {
        Article::search('Scout')
            ->controlledFor($this->ada)
            ->options(['filter_by' => 'featured:=true'])
            ->get();

        $this->assertSame($this->combined('featured:=true'), $this->onlyRequest()['filter_by']);
    }

    #[Test]
    public function it_ands_the_filter_by_of_typesense_search_parameters(): void
    {
        $this->typesenseSearchParameters(['filter_by' => 'featured:=true']);

        Article::search('Scout')->controlledFor($this->ada)->get();

        $this->assertSame($this->combined('featured:=true'), $this->onlyRequest()['filter_by']);
    }

    #[Test]
    public function it_ands_the_filter_by_of_the_options_that_scout_prefers_to_the_one_of_the_model(): void
    {
        $this->typesenseSearchParameters(['filter_by' => 'featured:=true']);

        Article::search('Scout')
            ->controlledFor($this->ada)
            ->options(['filter_by' => 'author_id:=7'])
            ->get();

        $this->assertSame($this->combined('author_id:=7'), $this->onlyRequest()['filter_by']);
    }

    #[Test]
    public function it_sends_the_access_filter_alone_for_a_blank_filter_by(): void
    {
        Article::search('Scout')
            ->options(['filter_by' => ''])
            ->controlledFor($this->ada)
            ->get();
        Article::search('Scout')
            ->controlledFor($this->ada)
            ->options(['filter_by' => '  '])
            ->get();

        $this->assertSame([$this->accessFilter(), $this->accessFilter()], array_column($this->typesense->requests, 'filter_by'));
    }

    #[Test]
    public function it_keeps_the_access_filter_on_every_read_path(): void
    {
        $search = Article::search('Scout')
            ->controlledFor($this->ada)
            ->options(['filter_by' => 'featured:=true']);

        $search->get();
        $search->keys();
        $search->raw();
        $search->cursor();
        $search->first();
        $search->paginate(10);
        $search->simplePaginate(10);
        $search->paginateRaw(10);
        $search->simplePaginateRaw(10);
        (clone $search)->take(300)->get();

        $this->assertCount(10, $this->typesense->requests);

        foreach ($this->typesense->requests as $request) {
            $this->assertSame($this->combined('featured:=true'), $request['filter_by']);
            $this->assertTrue($request['filter_curated_hits']);
        }
    }

    #[Test]
    public function it_hands_the_combined_filter_to_a_search_callback(): void
    {
        $received = null;

        Article::search('Scout', function (mixed $documents, string $query, array $options) use (&$received): array {
            $received = $options;

            return ['hits' => [], 'found' => 0];
        })
            ->options(['filter_by' => 'featured:=true'])
            ->controlledFor($this->ada)
            ->raw();

        $this->assertIsArray($received);
        $this->assertSame($this->combined('featured:=true'), $received['filter_by']);
        $this->assertTrue($received['filter_curated_hits']);
    }

    #[Test]
    public function it_combines_the_filter_once_however_often_the_search_runs(): void
    {
        $search = Article::search('Scout')
            ->options(['filter_by' => 'featured:=true'])
            ->controlledFor($this->ada);

        $search->get();
        $search->get();

        $this->assertSame([$this->combined('featured:=true'), $this->combined('featured:=true')], array_column($this->typesense->requests, 'filter_by'));
    }

    #[Test]
    public function it_combines_a_filter_by_that_the_app_changes_between_two_searches(): void
    {
        $search = Article::search('Scout')
            ->options(['filter_by' => 'featured:=true'])
            ->controlledFor($this->ada);

        $search->get();
        $search->options(['filter_by' => 'author_id:=7']);
        $search->get();

        $this->assertSame([$this->combined('featured:=true'), $this->combined('author_id:=7')], array_column($this->typesense->requests, 'filter_by'));
    }

    #[Test]
    public function it_puts_the_access_filter_back_when_the_app_replaces_the_options_after_controlled(): void
    {
        Article::search('Scout')
            ->options(['filter_by' => 'featured:=true'])
            ->controlledFor($this->ada)
            ->options(['per_page' => 5])
            ->get();

        $request = $this->onlyRequest();
        $this->assertSame($this->accessFilter(quoted: false), $request['filter_by']);
        $this->assertTrue($request['filter_curated_hits']);
    }

    #[Test]
    public function it_filters_the_curated_hits_whatever_the_app_passes(): void
    {
        $this->typesenseSearchParameters(['filter_curated_hits' => false]);

        Article::search('Scout')
            ->options(['pinned_hits' => '2:1', 'filter_curated_hits' => false])
            ->controlledFor($this->ada)
            ->get();
        Article::search('Scout')
            ->controlledFor($this->ada)
            ->options(['pinned_hits' => '2:1', 'filter_curated_hits' => 'false', 'preset' => 'open'])
            ->get();
        Article::search('Scout')
            ->controlledFor($this->ada)
            ->get();

        $this->assertSame([true, true, true], array_column($this->typesense->requests, 'filter_curated_hits'));
        $this->assertSame(['2:1', '2:1'], array_column($this->typesense->requests, 'pinned_hits'));
    }

    #[Test]
    public function it_leaves_the_filter_by_of_the_app_alone_for_an_unrestricted_reader(): void
    {
        $this->allow(['view global articles']);
        $this->typesenseSearchParameters(['filter_curated_hits' => false]);

        Article::search('Scout')
            ->options(['filter_by' => 'featured:=true'])
            ->controlledFor($this->ada)
            ->get();

        $request = $this->onlyRequest();
        $this->assertSame('featured:=true', $request['filter_by']);
        $this->assertFalse($request['filter_curated_hits']);
    }

    #[Test]
    public function it_ands_the_boundary_and_the_access_keys_after_the_filter_by_of_the_app(): void
    {
        ConfiguredArticleControl::bindWithin(
            Boundary::where('workspace_id', fn (User $user): ?int => $user->current_workspace_id),
            AuthorPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys('author', fn (Article $article): mixed => $article->author_id, fn (User $user): mixed => $user->getKey()),
        );

        Article::search('Scout')
            ->options(['filter_by' => 'featured:=true'])
            ->controlledFor($this->ada)
            ->get();

        $filter = $this->onlyRequest()['filter_by'];
        $this->assertSame($this->combined('featured:=true'), $filter);
        $this->assertStringContainsString('_boundary:=[', $filter);
        $this->assertStringContainsString(' && _access:=[', $filter);
    }

    #[Test]
    public function it_calls_no_engine_for_a_closed_reader_whatever_the_filter_by(): void
    {
        $this->typesenseSearchParameters(['filter_by' => 'featured:=true']);

        $before = Article::search('Scout')
            ->options(['filter_by' => 'id:*'])
            ->controlledFor(null)
            ->get();
        $after = Article::search('Scout')
            ->controlledFor(null)
            ->options(['filter_by' => 'id:*'])
            ->paginate(10);

        $this->assertCount(0, $before);
        $this->assertCount(0, $after);
        $this->assertSame([], $this->typesense->requests);
    }

    #[Test]
    public function it_leaves_an_uncontrolled_search_as_the_app_wrote_it(): void
    {
        Article::search('Scout')
            ->options(['filter_by' => 'featured:=true'])
            ->uncontrolled()
            ->get();

        $request = $this->onlyRequest();
        $this->assertSame('featured:=true', $request['filter_by']);
        $this->assertArrayNotHasKey('filter_curated_hits', $request);
    }

    /** @return array<string, array{mixed}> */
    public static function filtersThatAreNotStrings(): array
    {
        return [
            'null' => [null],
            'a list' => [['featured:=true']],
            'a boolean' => [false],
            'an integer' => [1],
        ];
    }

    #[Test]
    #[DataProvider('filtersThatAreNotStrings')]
    public function it_refuses_an_options_filter_by_that_is_not_a_string(mixed $filter): void
    {
        $search = Article::search('Scout')->controlledFor($this->ada)->options(['filter_by' => $filter]);

        $this->expectExceptionObject(UncombinableSearchFilterException::notAString(new Article, 'options()', $filter));

        try {
            $search->get();
        } finally {
            $this->assertSame([], $this->typesense->requests);
        }
    }

    #[Test]
    #[DataProvider('filtersThatAreNotStrings')]
    public function it_refuses_a_filter_by_of_typesense_search_parameters_that_is_not_a_string(mixed $filter): void
    {
        $this->typesenseSearchParameters(['filter_by' => $filter]);

        $this->expectExceptionObject(UncombinableSearchFilterException::notAString(new Article, 'typesenseSearchParameters()', $filter));

        Article::search('Scout')->controlledFor($this->ada);
    }

    /** @return array<string, array{string}> */
    public static function filtersThatDoNotEndOnTheirOwn(): array
    {
        return [
            'a quoted value left open' => ['title:=`Scout'],
            'a quoted value left open after a group' => ['id:*) || title:=`z'],
            'a backtick that starts a token' => ['`)a`'],
            'a quoted value inside an object filter' => ['o.{a:=`} ) && x:=`b'],
            'a quoted value inside a reference' => ['$C(k:=`v) && y:=`w)'],
            'a geo filter inside a quoted value' => ['title:=`a:(b`'],
            'a geo filter left open' => ['loc:(1, 2, 3 km'],
            'a reference left open' => ['$Authors(id:=1'],
            'an object filter left open' => ['address.{city:=Paris'],
        ];
    }

    #[Test]
    #[DataProvider('filtersThatDoNotEndOnTheirOwn')]
    public function it_refuses_an_options_filter_by_that_does_not_end_on_its_own(string $filter): void
    {
        $this->expectExceptionObject(UncombinableSearchFilterException::notSelfContained(new Article, 'options()', $filter));

        Article::search('Scout')->options(['filter_by' => $filter])->controlledFor($this->ada);
    }

    #[Test]
    #[DataProvider('filtersThatDoNotEndOnTheirOwn')]
    public function it_refuses_a_filter_by_of_typesense_search_parameters_that_does_not_end_on_its_own(string $filter): void
    {
        $this->typesenseSearchParameters(['filter_by' => $filter]);
        $this->expectExceptionObject(UncombinableSearchFilterException::notSelfContained(new Article, 'typesenseSearchParameters()', $filter));

        Article::search('Scout')->controlledFor($this->ada);
    }

    #[Test]
    public function it_refuses_a_filter_by_that_does_not_end_on_its_own_when_the_search_runs(): void
    {
        $search = Article::search('Scout')->controlledFor($this->ada)->options(['filter_by' => 'id:*) || title:=`z']);

        $this->expectException(UncombinableSearchFilterException::class);

        try {
            $search->paginate(10);
        } finally {
            $this->assertSame([], $this->typesense->requests);
        }
    }

    /** @return array<string, array{string}> */
    public static function selfContainedFilters(): array
    {
        return [
            'a parenthesis in a quoted value' => ['title:=`Scout (one`'],
            'parentheses in quoted values in any order' => ['title:=`a)b` || title:=`(c`'],
            'nested groups' => ['(featured:=true || author_id:=[1, 2]) && (title:=`x` || (author_id:>3))'],
            'a quoted value with operators' => ['title:=`a && b || c`'],
            'a geo filter' => ['location:(48.85, 2.34, 5 km)'],
            'a reference with a quoted value' => ['$Authors(name:=`Ada (Jr)`)'],
            'an object filter' => ['address.{city:=`Paris`}'],
            'a group closed and opened again' => ['status:=published) || (status:=draft'],
        ];
    }

    #[Test]
    #[DataProvider('selfContainedFilters')]
    public function it_ands_a_self_contained_filter_by_with_parentheses_or_backticks(string $filter): void
    {
        Article::search('Scout')
            ->options(['filter_by' => $filter])
            ->controlledFor($this->ada)
            ->get();

        $this->assertSame($this->combined($filter), $this->onlyRequest()['filter_by']);
    }
}

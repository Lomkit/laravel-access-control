<?php

namespace Lomkit\Access\Tests\Feature\Search\Guard;

use Laravel\Scout\Engines\AlgoliaEngine;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\InvalidSearchBodyException;
use Lomkit\Access\Exceptions\RefusedSearchFilterException;
use Lomkit\Access\Exceptions\RefusedSearchParameterException;
use Lomkit\Access\Search\Tokens\Syntax\AlgoliaSyntax;
use Lomkit\Access\Tests\Fixtures\SearchGuard\GuardTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class AlgoliaSearchGuardTest extends GuardTestCase
{
    #[\Override]
    protected function engineClass(): string
    {
        return AlgoliaEngine::class;
    }

    /**
     * @param  array<string, mixed>  $search
     * @return array<array-key, mixed>
     */
    protected function guard(array $search): array
    {
        $body = Access::guardSearch(['requests' => [['indexName' => $this->index(), ...$search]]], $this->ada);

        return $body['requests'][0];
    }

    /** @return array<array-key, mixed> */
    protected function parameters(string $query): array
    {
        $search = $this->guard(['params' => $query]);
        $this->assertIsString($search['params']);
        parse_str($search['params'], $parameters);

        return $parameters;
    }

    protected function access(): string
    {
        return (string) $this->accessFilter(new AlgoliaSyntax, $this->ada);
    }

    #[Test]
    public function it_ands_the_filters_of_the_browser_with_the_access_filter(): void
    {
        $search = $this->guard(['query' => 'Scout', 'hitsPerPage' => 20, 'facetFilters' => [['featured:true']], 'filters' => 'featured:true AND author_id:1']);

        $this->assertSame([
            'indexName' => $this->index(),
            'query' => 'Scout',
            'hitsPerPage' => 20,
            'facetFilters' => [['featured:true']],
            'filters' => "featured:true AND author_id:1 AND {$this->access()}",
            'enableRules' => false,
            'enableABTest' => false,
        ], $search);
    }

    #[Test]
    public function it_groups_filters_of_the_browser_that_hold_an_or(): void
    {
        $this->assertSame("(featured:true OR featured:false) AND {$this->access()}", $this->guard(['filters' => 'featured:true OR featured:false'])['filters']);
        $this->assertSame("(title:'a OR b') AND {$this->access()}", $this->guard(['filters' => "title:'a OR b'"])['filters']);
        $this->assertSame("author_id:1 AND (featured:true OR featured:false) AND {$this->access()}", $this->guard(['filters' => 'author_id:1 AND (featured:true OR featured:false)'])['filters']);
    }

    #[Test]
    public function it_sends_the_access_filter_alone_without_filters_of_the_browser(): void
    {
        $this->assertSame($this->access(), $this->guard([])['filters']);
        $this->assertSame($this->access(), $this->guard(['filters' => ''])['filters']);
    }

    #[Test]
    public function it_always_turns_the_rules_and_the_ab_tests_off(): void
    {
        $this->assertFalse($this->guard([])['enableRules']);
        $this->assertFalse($this->guard(['enableRules' => true])['enableRules']);
        $this->assertFalse($this->guard(['enableABTest' => true])['enableABTest']);
    }

    #[Test]
    public function it_keeps_the_filters_of_the_browser_as_they_are_for_an_unrestricted_reader(): void
    {
        $this->allow(['view global articles']);

        $this->assertSame('featured:true OR featured:false', $this->guard(['filters' => 'featured:true OR featured:false'])['filters']);
        $this->assertArrayNotHasKey('filters', $this->guard([]));
        $this->assertFalse($this->guard([])['enableRules']);
    }

    #[Test]
    public function it_guards_the_parameters_sent_as_a_query_string(): void
    {
        $parameters = $this->parameters('query=Scout%20primer&hitsPerPage=20&filters=featured%3Atrue%20OR%20featured%3Afalse&enableRules=true');

        $this->assertSame([
            'query' => 'Scout primer',
            'hitsPerPage' => '20',
            'filters' => "(featured:true OR featured:false) AND {$this->access()}",
            'enableRules' => 'false',
            'enableABTest' => 'false',
        ], $parameters);
    }

    #[Test]
    public function it_keeps_the_fields_of_a_search_for_facet_values(): void
    {
        $search = $this->guard(['type' => 'facet', 'facet' => 'author_id', 'params' => 'facetQuery=1']);

        $this->assertSame(['indexName' => $this->index(), 'type' => 'facet', 'facet' => 'author_id'], array_diff_key($search, ['params' => true]));
        $this->assertSame(['facetQuery' => '1', 'filters' => $this->access(), 'enableRules' => 'false', 'enableABTest' => 'false'], $this->parameters('facetQuery=1'));
    }

    #[Test]
    public function it_refuses_parameters_sent_both_as_a_query_string_and_as_fields(): void
    {
        $this->expectException(InvalidSearchBodyException::class);
        $this->expectExceptionMessage('sends its parameters both in [params] and as fields of the search');

        $this->guard(['params' => 'query=Scout', 'filters' => 'featured:true']);
    }

    /** @return array<string, array{mixed}> */
    public static function unparsableQueries(): array
    {
        return [
            'a parameter twice' => ['filters=a%3A1&filters=b%3A1'],
            'a parameter twice in another case' => ['filters=a%3A1&Filters=b%3A1'],
            'a parameter without a name' => ['=Scout'],
        ];
    }

    #[Test]
    #[DataProvider('unparsableQueries')]
    public function it_refuses_a_query_string_of_ambiguous_parameters(string $query): void
    {
        $this->expectException(InvalidSearchBodyException::class);
        $this->expectExceptionMessage('is not a query string of distinct, named parameters');

        $this->guard(['params' => $query]);
    }

    #[Test]
    public function it_refuses_a_query_string_that_is_not_a_string(): void
    {
        $this->expectException(InvalidSearchBodyException::class);
        $this->expectExceptionMessage('holds a [params] of an unexpected type');

        $this->guard(['params' => ['query' => 'Scout']]);
    }

    /** @return array<string, array{string, mixed}> */
    public static function refusedParameters(): array
    {
        return [
            'rule contexts' => ['ruleContexts', ['staff']],
            'a key' => ['x-algolia-api-key', 'key'],
            'an application' => ['X-Algolia-Application-Id', 'app'],
            'restricted indexes' => ['restrictIndices', 'other'],
            'restricted sources' => ['restrictSources', '0.0.0.0/0'],
            'referers' => ['referers', ['*']],
            'an expiry' => ['validUntil', 1],
            'the filters in another case' => ['Filters', 'featured:true'],
            'the rules in another case' => ['EnableRules', true],
            'the A/B tests in another case' => ['enableAbTest', true],
            'personalization' => ['enablePersonalization', true],
            'the impact of personalization' => ['personalizationImpact', 100],
            'a parameter the guard does not know' => ['futureParameter', true],
        ];
    }

    #[Test]
    #[DataProvider('refusedParameters')]
    public function it_refuses_a_parameter_that_is_not_one_it_accepts(string $parameter, mixed $value): void
    {
        $this->expectException(RefusedSearchParameterException::class);
        $this->expectExceptionMessage(sprintf('The Algolia search of the [%s] index sends [%s], which is not one of the parameters that a guarded search accepts', $this->index(), $parameter));

        $this->guard([$parameter => $value]);
    }

    /** @return array<string, array{string}> */
    public static function refusedQueryParameters(): array
    {
        return [
            'rule contexts' => ['ruleContexts=staff'],
            'another index' => ['indexName=other'],
            'nested parameters' => ['params=query%3DScout'],
            'a parameter with brackets' => ['filters%5B%5D=featured%3Atrue'],
            'rules by brackets' => ['enableRules%5B0%5D=true'],
        ];
    }

    #[Test]
    #[DataProvider('refusedQueryParameters')]
    public function it_refuses_a_parameter_of_the_query_string_that_could_bypass_the_access_filter(string $query): void
    {
        $this->expectException(RefusedSearchParameterException::class);

        $this->guard(['params' => $query]);
    }

    /** @return array<string, array{string}> */
    public static function unterminatedFilters(): array
    {
        return [
            'a parenthesis that closes early' => ['featured:true) OR (featured:false'],
            'a parenthesis left open' => ['(featured:true'],
            'a double quote left open' => ['title:"Scout'],
            'a single quote left open' => ["title:'Scout"],
            'a parenthesis that a single quote hides from one reading only' => ["title:'(' AND featured:true"],
            'a parenthesis that closes early inside double quotes' => ['title:"(" OR featured:true) OR (featured:"x)"'],
        ];
    }

    #[Test]
    #[DataProvider('unterminatedFilters')]
    public function it_refuses_filters_of_the_browser_that_do_not_terminate_on_their_own(string $filter): void
    {
        $this->expectException(RefusedSearchFilterException::class);
        $this->expectExceptionMessage(sprintf('The [filters] [%s] of the Algolia search of the [%s] index is not a self-contained filter.', $filter, $this->index()));

        $this->guard(['filters' => $filter]);
    }

    #[Test]
    public function it_refuses_filters_of_the_browser_that_hold_a_backslash(): void
    {
        $this->expectException(RefusedSearchFilterException::class);
        $this->expectExceptionMessage('holds a backslash');

        $this->guard(['filters' => 'title:"a\" OR featured:true']);
    }

    #[Test]
    public function it_refuses_filters_of_the_browser_that_are_not_a_string(): void
    {
        $this->expectException(RefusedSearchFilterException::class);
        $this->expectExceptionMessage('must be a string');

        $this->guard(['filters' => ['featured:true']]);
    }

    #[Test]
    public function it_keeps_the_strategy_of_the_queries(): void
    {
        $body = Access::guardSearch(['strategy' => 'stopIfEnoughMatches', 'requests' => [['indexName' => $this->index()]]], $this->ada);

        $this->assertSame('stopIfEnoughMatches', $body['strategy']);
    }

    #[Test]
    public function it_refuses_a_field_of_the_body_it_does_not_know(): void
    {
        $this->expectException(InvalidSearchBodyException::class);
        $this->expectExceptionMessage('The Algolia search body holds the field [apiKey], which a guarded search does not accept.');

        Access::guardSearch(['apiKey' => 'key', 'requests' => [['indexName' => $this->index()]]], $this->ada);
    }

    #[Test]
    public function it_accepts_the_parameters_of_the_instantsearch_client(): void
    {
        $parameters = $this->parameters('query=Scout&hitsPerPage=20&page=0&maxValuesPerFacet=10&facets=%5B%22featured%22%5D&tagFilters=&facetFilters=%5B%5B%22featured%3Atrue%22%5D%5D&highlightPreTag=%3Cmark%3E&highlightPostTag=%3C%2Fmark%3E&clickAnalytics=true&userToken=anonymous-1');

        $this->assertSame($this->access(), $parameters['filters']);
        $this->assertSame('[["featured:true"]]', $parameters['facetFilters']);
        $this->assertSame('anonymous-1', $parameters['userToken']);
    }

    #[Test]
    public function it_hides_the_soft_deleted_documents_when_scout_keeps_them(): void
    {
        config(['scout.soft_delete' => true]);

        $this->assertSame("featured:true AND (_access:'author.{$this->ada->getKey()}') AND __soft_deleted:'0'", $this->guard(['filters' => 'featured:true'])['filters']);
    }
}

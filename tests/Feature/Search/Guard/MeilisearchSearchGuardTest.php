<?php

namespace Lomkit\Access\Tests\Feature\Search\Guard;

use Laravel\Scout\Engines\MeilisearchEngine;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\InvalidSearchBodyException;
use Lomkit\Access\Exceptions\RefusedSearchFilterException;
use Lomkit\Access\Exceptions\RefusedSearchParameterException;
use Lomkit\Access\Search\Tokens\Syntax\MeilisearchSyntax;
use Lomkit\Access\Tests\Fixtures\SearchGuard\GuardTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class MeilisearchSearchGuardTest extends GuardTestCase
{
    #[\Override]
    protected function engineClass(): string
    {
        return MeilisearchEngine::class;
    }

    /**
     * @param  array<string, mixed>  $search
     * @return array<array-key, mixed>
     */
    protected function guard(array $search): array
    {
        $body = Access::guardSearch(['queries' => [['indexUid' => $this->index(), ...$search]]], $this->ada);

        return $body['queries'][0];
    }

    protected function access(): string
    {
        return (string) $this->accessFilter(new MeilisearchSyntax, $this->ada);
    }

    /** @return array<string, array{mixed, list<mixed>}> */
    public static function clientFilters(): array
    {
        return [
            'no filter' => [null, []],
            'a blank filter' => ['', []],
            'an empty list' => [[], []],
            'a string' => ['featured = true OR featured = false', ['featured = true OR featured = false']],
            'a list of strings' => [['featured = true', 'author_id = 1'], ['featured = true', 'author_id = 1']],
            'a list with a disjunction' => [[['featured = true', 'featured = false'], 'author_id = 1'], [['featured = true', 'featured = false'], 'author_id = 1']],
        ];
    }

    /** @param  list<mixed>  $clauses */
    #[Test]
    #[DataProvider('clientFilters')]
    public function it_ands_the_filter_of_the_browser_with_the_access_filter_as_a_list(mixed $filter, array $clauses): void
    {
        $search = $this->guard(['q' => 'Scout', 'limit' => 20, 'filter' => $filter]);

        $this->assertSame(['indexUid' => $this->index(), 'q' => 'Scout', 'limit' => 20, 'filter' => [$this->access(), ...$clauses]], $search);
    }

    #[Test]
    public function it_keeps_the_filter_of_the_browser_as_it_is_for_an_unrestricted_reader(): void
    {
        $this->allow(['view global articles']);

        $this->assertSame('featured = true', $this->guard(['filter' => 'featured = true'])['filter']);
        $this->assertArrayNotHasKey('filter', $this->guard([]));
    }

    /** @return array<string, array{mixed}> */
    public static function invalidFilters(): array
    {
        return [
            'a number' => [1],
            'an object' => [['featured' => 'true']],
            'a list with a number' => [['featured = true', 1]],
            'a disjunction with a list' => [[[['featured = true']]]],
        ];
    }

    #[Test]
    #[DataProvider('invalidFilters')]
    public function it_refuses_a_filter_of_the_browser_that_is_neither_a_string_nor_a_list(mixed $filter): void
    {
        $this->expectException(RefusedSearchFilterException::class);
        $this->expectExceptionMessage('must be a string, or a list of strings and lists of strings');

        $this->guard(['filter' => $filter]);
    }

    /** @return array<string, array{string, mixed}> */
    public static function refusedParameters(): array
    {
        return [
            'an inline rule' => ['inlineRule', ['uid' => 'rule']],
            'the network' => ['useNetwork', true],
            'the filter in another case' => ['Filter', 'featured = true'],
            'the index in another case' => ['IndexUid', 'other'],
            'a parameter the guard does not know' => ['pinnedDocuments', ['1']],
        ];
    }

    #[Test]
    #[DataProvider('refusedParameters')]
    public function it_refuses_a_parameter_that_is_not_one_it_accepts(string $parameter, mixed $value): void
    {
        $this->expectException(RefusedSearchParameterException::class);
        $this->expectExceptionMessage(sprintf('The Meilisearch search of the [%s] index sends [%s], which is not one of the parameters that a guarded search accepts', $this->index(), $parameter));

        $this->guard([$parameter => $value]);
    }

    #[Test]
    public function it_refuses_a_search_sent_to_a_remote_instance(): void
    {
        $this->expectException(RefusedSearchParameterException::class);
        $this->expectExceptionMessage('sends [federationOptions.remote]');

        $this->guard(['federationOptions' => ['weight' => 2, 'remote' => 'other']]);
    }

    #[Test]
    public function it_keeps_the_federation_of_the_searches(): void
    {
        $body = Access::guardSearch([
            'federation' => ['limit' => 10, 'facetsByIndex' => [$this->index() => ['featured']], 'mergeFacets' => []],
            'queries' => [['indexUid' => $this->index(), 'federationOptions' => ['weight' => 2]]],
        ], $this->ada);

        $this->assertSame(['limit' => 10, 'facetsByIndex' => [$this->index() => ['featured']], 'mergeFacets' => []], $body['federation']);
        $this->assertSame(['indexUid' => $this->index(), 'federationOptions' => ['weight' => 2], 'filter' => [$this->access()]], $body['queries'][0]);
    }

    #[Test]
    public function it_refuses_federation_options_that_are_not_an_object(): void
    {
        $this->expectException(InvalidSearchBodyException::class);
        $this->expectExceptionMessage('holds a [federationOptions] of an unexpected type');

        $this->guard(['federationOptions' => 'remote']);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function unknownFields(): array
    {
        return [
            'a field of the body' => [['network' => true], 'network'],
            'a field of the federation' => [['federation' => ['remotes' => ['other']]], 'federation.remotes'],
        ];
    }

    /** @param  array<string, mixed>  $fields */
    #[Test]
    #[DataProvider('unknownFields')]
    public function it_refuses_a_field_of_the_body_it_does_not_know(array $fields, string $field): void
    {
        $this->expectException(InvalidSearchBodyException::class);
        $this->expectExceptionMessage("The Meilisearch search body holds the field [{$field}], which a guarded search does not accept.");

        Access::guardSearch([...$fields, 'queries' => [['indexUid' => $this->index()]]], $this->ada);
    }

    #[Test]
    public function it_accepts_the_parameters_of_the_instantsearch_client(): void
    {
        $parameters = ['q' => 'Scout', 'facets' => ['featured'], 'attributesToHighlight' => ['*'], 'highlightPreTag' => '__ais-highlight__', 'highlightPostTag' => '__/ais-highlight__', 'limit' => 21, 'offset' => 0, 'attributesToCrop' => ['title:20'], 'cropMarker' => '...', 'sort' => ['title:asc']];

        $this->assertSame(['indexUid' => $this->index(), ...$parameters, 'filter' => [$this->access(), ['featured = true']]], $this->guard([...$parameters, 'filter' => [['featured = true']]]));
    }

    #[Test]
    public function it_hides_the_soft_deleted_documents_when_scout_keeps_them(): void
    {
        config(['scout.soft_delete' => true]);

        $this->assertSame(["{$this->access()} AND __soft_deleted = 0", 'featured = true'], $this->guard(['filter' => 'featured = true'])['filter']);
    }
}

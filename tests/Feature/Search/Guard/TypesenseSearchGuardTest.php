<?php

namespace Lomkit\Access\Tests\Feature\Search\Guard;

use Laravel\Scout\Engines\TypesenseEngine;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\InvalidSearchBodyException;
use Lomkit\Access\Exceptions\RefusedSearchFilterException;
use Lomkit\Access\Exceptions\RefusedSearchParameterException;
use Lomkit\Access\Search\Tokens\Syntax\TypesenseSyntax;
use Lomkit\Access\Tests\Fixtures\SearchGuard\GuardTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class TypesenseSearchGuardTest extends GuardTestCase
{
    #[\Override]
    protected function engineClass(): string
    {
        return TypesenseEngine::class;
    }

    /**
     * @param  array<string, mixed>  $search
     * @return array<array-key, mixed>
     */
    protected function guard(array $search): array
    {
        $body = Access::guardSearch(['searches' => [['collection' => $this->index(), ...$search]]], $this->ada);

        return $body['searches'][0];
    }

    protected function access(): string
    {
        return (string) $this->accessFilter(new TypesenseSyntax, $this->ada);
    }

    #[Test]
    public function it_ands_the_filter_of_the_browser_with_the_access_filter(): void
    {
        $search = $this->guard(['q' => 'Scout', 'query_by' => 'title', 'per_page' => 20, 'filter_by' => 'featured:=true || featured:=false']);

        $this->assertSame([
            'collection' => $this->index(),
            'q' => 'Scout',
            'query_by' => 'title',
            'per_page' => 20,
            'filter_by' => "(featured:=true || featured:=false) && ({$this->access()})",
            'filter_curated_hits' => true,
        ], $search);
    }

    #[Test]
    public function it_sends_the_access_filter_alone_without_a_filter_of_the_browser(): void
    {
        $this->assertSame($this->access(), $this->guard([])['filter_by']);
        $this->assertSame($this->access(), $this->guard(['filter_by' => ' '])['filter_by']);
    }

    #[Test]
    public function it_always_filters_the_curated_hits(): void
    {
        $this->assertTrue($this->guard([])['filter_curated_hits']);
        $this->assertTrue($this->guard(['filter_curated_hits' => false])['filter_curated_hits']);
        $this->assertTrue($this->guard(['filter_curated_hits' => 'false'])['filter_curated_hits']);
    }

    #[Test]
    public function it_keeps_parentheses_and_backticks_that_are_inside_quoted_values(): void
    {
        $this->assertSame("(title:=`a) || (b` && featured:=true) && ({$this->access()})", $this->guard(['filter_by' => 'title:=`a) || (b` && featured:=true'])['filter_by']);
        $this->assertSame("(title:=`\$authors(x)`) && ({$this->access()})", $this->guard(['filter_by' => 'title:=`$authors(x)`'])['filter_by']);
    }

    /** @return array<string, array{string}> */
    public static function unterminatedFilters(): array
    {
        return [
            'a backtick left open' => ['title:=`Scout'],
            'a backtick left open after a group' => ['id:*) || title:=`z'],
            'a reference left open' => ['$authors(id:=1'],
            'an operator that starts the filter' => ['& featured:=true'],
        ];
    }

    #[Test]
    #[DataProvider('unterminatedFilters')]
    public function it_refuses_a_filter_of_the_browser_that_does_not_terminate_on_its_own(string $filter): void
    {
        $this->expectException(RefusedSearchFilterException::class);
        $this->expectExceptionMessage(sprintf('The [filter_by] [%s] of the Typesense search of the [%s] index is not a self-contained filter.', $filter, $this->index()));

        $this->guard(['filter_by' => $filter]);
    }

    #[Test]
    public function it_ends_the_combined_filter_with_the_access_filter_for_a_filter_that_closes_and_opens_a_group(): void
    {
        $this->assertSame("(featured:=true) || (featured:=false) && ({$this->access()})", $this->guard(['filter_by' => 'featured:=true) || (featured:=false'])['filter_by']);
    }

    #[Test]
    public function it_refuses_a_filter_of_the_browser_that_is_not_a_string(): void
    {
        $this->expectException(RefusedSearchFilterException::class);
        $this->expectExceptionMessage('must be a string');

        $this->guard(['filter_by' => ['featured:=true']]);
    }

    /** @return array<string, array{string, mixed}> */
    public static function refusedParameters(): array
    {
        return [
            'pinned hits' => ['pinned_hits', '1:1'],
            'a preset' => ['preset', 'everything'],
            'curations switched on' => ['enable_overrides', true],
            'curations by tag' => ['override_tags', 'staff'],
            'curations switched on by their new name' => ['enable_curations', true],
            'curations by tag by their new name' => ['curation_tags', 'staff'],
            'a conversation of another search' => ['conversation_id', 'conversation'],
            'a key' => ['x-typesense-api-key', 'key'],
            'a key in capitals' => ['X-TYPESENSE-API-KEY', 'key'],
            'a user' => ['x-typesense-user-id', 'user'],
            'the filter in another case' => ['Filter_By', 'featured:=true'],
            'the curated hits in another case' => ['FILTER_CURATED_HITS', false],
            'the collection in another case' => ['Collection', 'other'],
        ];
    }

    #[Test]
    #[DataProvider('refusedParameters')]
    public function it_refuses_a_parameter_that_could_bypass_the_access_filter(string $parameter, mixed $value): void
    {
        $this->expectException(RefusedSearchParameterException::class);
        $this->expectExceptionMessage(sprintf('The Typesense search of the [%s] index sends [%s], which a guarded search refuses', $this->index(), $parameter));

        $this->guard([$parameter => $value]);
    }

    /** @return array<string, array{string, string}> */
    public static function joins(): array
    {
        return [
            'a filter on another collection' => ['filter_by', '$authors(name:=Ada)'],
            'a filter on another collection after a quoted value' => ['filter_by', 'title:=`x` && $authors (id:*)'],
            'the fields of another collection' => ['include_fields', 'title, $authors(*)'],
            'the excluded fields of another collection' => ['exclude_fields', '$authors(email)'],
            'a sort on another collection' => ['sort_by', '$authors(name:asc)'],
            'the facets of another collection' => ['facet_by', '$authors(name)'],
            'the groups of another collection' => ['group_by', '$authors(name)'],
        ];
    }

    #[Test]
    #[DataProvider('joins')]
    public function it_refuses_a_reference_to_another_collection(string $parameter, string $value): void
    {
        $this->expectException(RefusedSearchParameterException::class);
        $this->expectExceptionMessage(sprintf('The [%s] of the Typesense search of the [%s] index references another collection', $parameter, $this->index()));

        $this->guard([$parameter => $value]);
    }

    #[Test]
    public function it_refuses_a_field_of_the_body_besides_the_searches_and_their_union(): void
    {
        $this->expectException(InvalidSearchBodyException::class);
        $this->expectExceptionMessage('The Typesense search body holds the field [preset], which a guarded search does not accept.');

        Access::guardSearch(['preset' => 'everything', 'searches' => [['collection' => $this->index()]]], $this->ada);
    }
}

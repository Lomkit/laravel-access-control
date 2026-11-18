<?php

namespace Lomkit\Access\Tests\Unit\Search\Tokens;

use Lomkit\Access\Search\SearchFilter;
use Lomkit\Access\Search\SearchKeyDigest;
use Lomkit\Access\Search\Tokens\Syntax\AlgoliaSyntax;
use Lomkit\Access\Search\Tokens\Syntax\FilterSyntax;
use Lomkit\Access\Search\Tokens\Syntax\MeilisearchSyntax;
use Lomkit\Access\Search\Tokens\Syntax\TypesenseSyntax;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class FilterSyntaxTest extends PackageTestCase
{
    /** @return array<string, array{FilterSyntax, string}> */
    public static function closedFilters(): array
    {
        return [
            'Meilisearch' => [new MeilisearchSyntax, '_access IN ["#closed"]'],
            'Typesense' => [new TypesenseSyntax, '_access:=[`#closed`]'],
            'Algolia' => [new AlgoliaSyntax, "(_access:'#closed')"],
        ];
    }

    #[Test]
    #[DataProvider('closedFilters')]
    public function it_renders_a_closed_filter_as_a_key_that_no_document_can_carry(FilterSyntax $syntax, string $expected): void
    {
        $this->assertSame($expected, $syntax->render(SearchFilter::closed()));
        $this->assertSame($expected, $syntax->render(SearchFilter::of(null, [])));
        $this->assertSame($expected, $syntax->render(SearchFilter::of([], null)));
        $this->assertDoesNotMatchRegularExpression('/^[0-9a-f]{32}$/', '#closed');
    }

    /** @return array<string, array{FilterSyntax}> */
    public static function syntaxes(): array
    {
        return [
            'Meilisearch' => [new MeilisearchSyntax],
            'Typesense' => [new TypesenseSyntax],
            'Algolia' => [new AlgoliaSyntax],
        ];
    }

    #[Test]
    #[DataProvider('syntaxes')]
    public function it_renders_no_filter_for_an_unrestricted_reader_without_a_boundary(FilterSyntax $syntax): void
    {
        $this->assertNull($syntax->render(SearchFilter::of(null, null)));
    }

    /** @return array<string, array{FilterSyntax, string}> */
    public static function accessFilters(): array
    {
        return [
            'Meilisearch' => [new MeilisearchSyntax, '_access IN ["%s", "%s"]'],
            'Typesense' => [new TypesenseSyntax, '_access:=[`%s`, `%s`]'],
            'Algolia' => [new AlgoliaSyntax, "(_access:'%s' OR _access:'%s')"],
        ];
    }

    #[Test]
    #[DataProvider('accessFilters')]
    public function it_renders_the_digests_of_the_access_keys(FilterSyntax $syntax, string $expected): void
    {
        $this->assertSame(
            sprintf($expected, SearchKeyDigest::of('client.7'), SearchKeyDigest::of('author._416461')),
            $syntax->render(SearchFilter::of(null, ['client.7', 'author._416461'])),
        );
    }

    /** @return array<string, array{FilterSyntax, string}> */
    public static function boundedFilters(): array
    {
        return [
            'Meilisearch' => [new MeilisearchSyntax, '_boundary IN ["%s"] AND _access IN ["%s", "%s"]'],
            'Typesense' => [new TypesenseSyntax, '_boundary:=[`%s`] && _access:=[`%s`, `%s`]'],
            'Algolia' => [new AlgoliaSyntax, "(_boundary:'%s') AND (_access:'%s' OR _access:'%s')"],
        ];
    }

    #[Test]
    #[DataProvider('boundedFilters')]
    public function it_renders_the_boundary_and_the_access_keys(FilterSyntax $syntax, string $expected): void
    {
        $this->assertSame(
            sprintf($expected, ...SearchKeyDigest::all(['workspace.1', 'client.7', 'featured.yes'])),
            $syntax->render(SearchFilter::of(['workspace.1'], ['client.7', 'featured.yes'])),
        );
    }

    /** @return array<string, array{FilterSyntax, string}> */
    public static function boundaryFilters(): array
    {
        return [
            'Meilisearch' => [new MeilisearchSyntax, '_boundary IN ["%s"]'],
            'Typesense' => [new TypesenseSyntax, '_boundary:=[`%s`]'],
            'Algolia' => [new AlgoliaSyntax, "(_boundary:'%s')"],
        ];
    }

    #[Test]
    #[DataProvider('boundaryFilters')]
    public function it_renders_only_the_boundary_for_an_unrestricted_reader_inside_a_boundary(FilterSyntax $syntax, string $expected): void
    {
        $this->assertSame(sprintf($expected, SearchKeyDigest::of('workspace.1')), $syntax->render(SearchFilter::of(['workspace.1'], null)));
    }

    #[Test]
    #[DataProvider('syntaxes')]
    public function it_never_renders_a_readable_key_or_a_character_the_engine_would_read(FilterSyntax $syntax): void
    {
        $filter = (string) $syntax->render(SearchFilter::of(null, ['a"b\\c', 'x, y) || (z', 'it\'s', 'a`b']));

        foreach (['a"b', 'b\\c', 'x, y', '|| (z', 'it\'s', 'a`b'] as $readable) {
            $this->assertStringNotContainsString($readable, $filter);
        }

        foreach (SearchKeyDigest::all(['a"b\\c', 'x, y) || (z', 'it\'s', 'a`b']) as $digest) {
            $this->assertStringContainsString($digest, $filter);
        }
    }
}

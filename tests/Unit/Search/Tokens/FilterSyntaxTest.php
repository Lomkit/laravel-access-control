<?php

namespace Lomkit\Access\Tests\Unit\Search\Tokens;

use Lomkit\Access\Exceptions\UnquotableSearchKeyException;
use Lomkit\Access\Search\SearchFilter;
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
            'Meilisearch' => [new MeilisearchSyntax, '_access IN ["client.7", "author._416461"]'],
            'Typesense' => [new TypesenseSyntax, '_access:=[`client.7`, `author._416461`]'],
            'Algolia' => [new AlgoliaSyntax, "(_access:'client.7' OR _access:'author._416461')"],
        ];
    }

    #[Test]
    #[DataProvider('accessFilters')]
    public function it_renders_the_access_keys_with_a_dot_and_an_escaped_value(FilterSyntax $syntax, string $expected): void
    {
        $this->assertSame($expected, $syntax->render(SearchFilter::of(null, ['client.7', 'author._416461'])));
    }

    /** @return array<string, array{FilterSyntax, string}> */
    public static function boundedFilters(): array
    {
        return [
            'Meilisearch' => [new MeilisearchSyntax, '_boundary IN ["workspace.1"] AND _access IN ["client.7", "featured.yes"]'],
            'Typesense' => [new TypesenseSyntax, '_boundary:=[`workspace.1`] && _access:=[`client.7`, `featured.yes`]'],
            'Algolia' => [new AlgoliaSyntax, "(_boundary:'workspace.1') AND (_access:'client.7' OR _access:'featured.yes')"],
        ];
    }

    #[Test]
    #[DataProvider('boundedFilters')]
    public function it_renders_the_boundary_and_the_access_keys(FilterSyntax $syntax, string $expected): void
    {
        $this->assertSame($expected, $syntax->render(SearchFilter::of(['workspace.1'], ['client.7', 'featured.yes'])));
    }

    /** @return array<string, array{FilterSyntax, string}> */
    public static function boundaryFilters(): array
    {
        return [
            'Meilisearch' => [new MeilisearchSyntax, '_boundary IN ["workspace.1"]'],
            'Typesense' => [new TypesenseSyntax, '_boundary:=[`workspace.1`]'],
            'Algolia' => [new AlgoliaSyntax, "(_boundary:'workspace.1')"],
        ];
    }

    #[Test]
    #[DataProvider('boundaryFilters')]
    public function it_renders_only_the_boundary_for_an_unrestricted_reader_inside_a_boundary(FilterSyntax $syntax, string $expected): void
    {
        $this->assertSame($expected, $syntax->render(SearchFilter::of(['workspace.1'], null)));
    }

    /** @return array<string, array{FilterSyntax, string}> */
    public static function quotedValues(): array
    {
        return [
            'Meilisearch' => [new MeilisearchSyntax, '_access IN ["a\\"b\\\\c", "x, y) || (z"]'],
            'Typesense' => [new TypesenseSyntax, '_access:=[`a"b\\c`, `x, y) || (z`]'],
            'Algolia' => [new AlgoliaSyntax, "(_access:'a\"b\\\\c' OR _access:'x, y) || (z')"],
        ];
    }

    #[Test]
    #[DataProvider('quotedValues')]
    public function it_quotes_every_value_with_the_rules_of_the_engine(FilterSyntax $syntax, string $expected): void
    {
        $this->assertSame($expected, $syntax->render(SearchFilter::of(null, ['a"b\\c', 'x, y) || (z'])));
    }

    #[Test]
    public function it_escapes_a_single_quote_for_algolia(): void
    {
        $this->assertSame("(_access:'it\\'s')", (new AlgoliaSyntax)->render(SearchFilter::of(null, ["it's"])));
    }

    #[Test]
    public function it_refuses_a_value_with_a_backtick_for_typesense(): void
    {
        $this->expectException(UnquotableSearchKeyException::class);
        $this->expectExceptionMessage('The search key [a`b] holds a backtick, which a Typesense filter cannot quote');

        (new TypesenseSyntax)->render(SearchFilter::of(null, ['a`b']));
    }
}

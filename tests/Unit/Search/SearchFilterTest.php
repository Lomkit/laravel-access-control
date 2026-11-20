<?php

namespace Lomkit\Access\Tests\Unit\Search;

use Lomkit\Access\Exceptions\UndigestedSearchValueException;
use Lomkit\Access\Search\SearchFilter;
use Lomkit\Access\Search\SearchKeyDigest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SearchFilterTest extends TestCase
{
    #[Test]
    public function it_keeps_the_readable_keys_for_the_parents_and_sends_their_digests_to_the_engine(): void
    {
        $filter = SearchFilter::of(['workspace_id.1'], ['author.1.23', 'featured.yes']);

        $this->assertSame(['workspace_id.1'], $filter->readableBoundaryKeys());
        $this->assertSame(['author.1.23', 'featured.yes'], $filter->readableAccessKeys());
        $this->assertSame([
            '_boundary' => SearchKeyDigest::all(['workspace_id.1']),
            '_access' => SearchKeyDigest::all(['author.1.23', 'featured.yes']),
        ], $filter->whereIns());
    }

    #[Test]
    public function it_sends_no_field_that_the_reader_is_not_restricted_on(): void
    {
        $this->assertSame([], SearchFilter::of(null, null)->whereIns());
        $this->assertSame(['_access' => SearchKeyDigest::all(['client.7'])], SearchFilter::of(null, ['client.7'])->whereIns());
    }

    #[Test]
    public function it_matches_a_document_by_the_digests_that_the_engine_stores(): void
    {
        $filter = SearchFilter::of(['workspace_id.1'], ['author.1.23']);

        $this->assertTrue($filter->matches(['_boundary' => SearchKeyDigest::all(['workspace_id.1']), '_access' => SearchKeyDigest::all(['author.1.23', 'featured.yes'])]));
        $this->assertFalse($filter->matches(['_boundary' => SearchKeyDigest::all(['workspace_id.1']), '_access' => SearchKeyDigest::all(['author.12.3'])]));
        $this->assertFalse($filter->matches(['_boundary' => SearchKeyDigest::all(['workspace_id.2']), '_access' => SearchKeyDigest::all(['author.1.23'])]));
        $this->assertFalse($filter->matches(['_boundary' => SearchKeyDigest::all(['workspace_id.1']), '_access' => []]));
    }

    #[Test]
    public function it_matches_nothing_once_closed(): void
    {
        $this->assertTrue(SearchFilter::of([], ['author.1.23'])->isClosed());
        $this->assertFalse(SearchFilter::closed()->matches(['_boundary' => [], '_access' => []]));
    }

    /** @return array<string, array{SearchFilter, array<string, list<string>>, string, string}> */
    public static function undigestedDocuments(): array
    {
        $boundary = SearchKeyDigest::all(['workspace_id.1']);

        return [
            'readable access keys' => [SearchFilter::of(['workspace_id.1'], ['author.1.23']), ['_boundary' => $boundary, '_access' => ['author.1.23']], '_access', 'author.1.23'],
            'a readable boundary key' => [SearchFilter::of(['workspace_id.1'], ['author.1.23']), ['_boundary' => ['workspace_id.1'], '_access' => SearchKeyDigest::all(['author.1.23'])], '_boundary', 'workspace_id.1'],
            'a readable key beside a digest' => [SearchFilter::of(['workspace_id.1'], ['author.1.23']), ['_boundary' => $boundary, '_access' => [...SearchKeyDigest::all(['author.1.23']), 'featured.yes']], '_access', 'featured.yes'],
            'an uppercase digest' => [SearchFilter::of(['workspace_id.1'], ['author.1.23']), ['_boundary' => $boundary, '_access' => [strtoupper(SearchKeyDigest::of('author.1.23'))]], '_access', strtoupper(SearchKeyDigest::of('author.1.23'))],
            'a field the reader is not restricted on' => [SearchFilter::of(null, null), ['_access' => ['author.1.23']], '_access', 'author.1.23'],
            'a closed filter' => [SearchFilter::closed(), ['_access' => ['author.1.23']], '_access', 'author.1.23'],
        ];
    }

    /** @param  array<string, list<string>>  $document */
    #[Test]
    #[DataProvider('undigestedDocuments')]
    public function it_refuses_to_match_a_document_value_that_is_not_a_digest(SearchFilter $filter, array $document, string $field, string $value): void
    {
        $this->expectException(UndigestedSearchValueException::class);
        $this->expectExceptionMessage(sprintf('The [%s] field of a search document holds the value [%s], which is not the digest of a search key, so no filter would ever match it. The package writes every search key in a document, and renders it in a filter, as its digest only, so this value is a defect of the package.', $field, $value));

        $filter->matches($document);
    }
}

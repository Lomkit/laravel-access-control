<?php

namespace Lomkit\Access\Tests\Unit\Search;

use Lomkit\Access\Search\SearchFilter;
use Lomkit\Access\Search\SearchKeyDigest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SearchFilterTest extends TestCase
{
    #[Test]
    public function it_keeps_the_readable_keys_for_the_parents_and_sends_their_digests_to_the_engine(): void
    {
        $filter = SearchFilter::of(['workspace_id.1'], ['author.1.23', 'featured.yes']);

        $this->assertSame(['workspace_id.1'], $filter->boundaryKeys());
        $this->assertSame(['author.1.23', 'featured.yes'], $filter->accessKeys());
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
        $this->assertFalse($filter->matches(['_boundary' => ['workspace_id.1'], '_access' => ['author.1.23']]));
    }

    #[Test]
    public function it_matches_nothing_once_closed(): void
    {
        $this->assertTrue(SearchFilter::of([], ['author.1.23'])->isClosed());
        $this->assertFalse(SearchFilter::closed()->matches(['_boundary' => [], '_access' => []]));
    }
}

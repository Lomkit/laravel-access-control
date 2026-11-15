<?php

namespace Lomkit\Access\Tests\Feature\RestApi\Search;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('rest-api')]
#[Group('scout')]
class SqlRestrictedSearchTest extends RestApiSearchTestCase
{
    /** @return array<string, array{string}> */
    public static function drivers(): array
    {
        return ['database' => ['database'], 'collection' => ['collection']];
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_restricts_the_search_in_sql_on_the_drivers_that_query_the_table(string $driver): void
    {
        config(['scout.driver' => $driver]);
        $this->grant(['view client threads' => ['Ada', 'Linus'], 'view own replies' => ['Ada']]);

        $this->assertSame(['Roadmap'], $this->found('threads', $this->ada, 'o', 'title'));
        $this->assertSame(['Pricing'], $this->found('threads', $this->linus, 'Pri', 'title'));
        $this->assertSame(['Ship it', 'Too expensive', 'Typo in the title'], $this->found('replies', $this->ada, 'i', 'body'));
        $this->assertSame([], $this->index->searches);
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_counts_only_the_rows_that_the_control_shows(string $driver): void
    {
        config(['scout.driver' => $driver]);
        $this->grant(['view client threads' => ['Ada']]);

        $this->textSearch('threads', $this->ada, 'o')
            ->assertOk()
            ->assertJsonPath('total', 1);
    }
}

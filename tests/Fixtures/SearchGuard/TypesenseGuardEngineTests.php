<?php

namespace Lomkit\Access\Tests\Fixtures\SearchGuard;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\RefusedSearchFilterException;
use Lomkit\Access\Exceptions\RefusedSearchParameterException;
use Lomkit\Access\Search\Tokens\SearchTokenKey;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use PHPUnit\Framework\Attributes\Test;

trait TypesenseGuardEngineTests
{
    private ?SearchTokenKey $guardedSearchKey = null;

    abstract protected function searchOnlyKey(string $index): SearchTokenKey;

    abstract protected function curateInternalsFirst(): void;

    /**
     * @param  array<string, mixed>  $search
     * @return list<string>
     */
    protected function guardedTitles(array $search, ?User $reader): array
    {
        Access::discoverControlsIn([__DIR__]);
        $index = (new GuardedArticle)->searchableAs();
        $body = Access::guardSearch(['searches' => [['collection' => $index, 'q' => 'Scout', 'query_by' => 'title', 'per_page' => 100, ...$search]]], $reader);
        $node = config('scout.typesense.client-settings.nodes.0');
        $result = Http::baseUrl("{$node['protocol']}://{$node['host']}:{$node['port']}")
            ->withHeaders(['X-TYPESENSE-API-KEY' => ($this->guardedSearchKey ??= $this->searchOnlyKey($index))->key()])
            ->post('/multi_search', $body)
            ->throw()
            ->json('results.0');

        $this->assertArrayNotHasKey('error', $result);
        $titles = array_map(fn (array $hit): string => $hit['document']['title'], $result['hits']);
        sort($titles);

        return $titles;
    }

    #[Test]
    public function it_finds_through_the_guard_only_the_rows_of_the_reader_whatever_the_filter_of_the_browser(): void
    {
        $this->allow(['view own articles']);

        $this->assertSame(['Scout primer'], $this->guardedTitles([], $this->ada));
        $this->assertSame(['Scout primer'], $this->guardedTitles(['filter_by' => 'featured:=true || featured:=false'], $this->ada));
        $this->assertSame(['Scout primer'], $this->guardedTitles(['filter_by' => '(featured:=true) || (featured:=false)'], $this->ada));
        $this->assertSame(['Scout primer'], $this->guardedTitles(['filter_by' => 'featured:=true) || (featured:=false'], $this->ada));
        $this->assertSame([], $this->guardedTitles(['filter_by' => 'featured:=true'], $this->ada));
    }

    #[Test]
    public function it_finds_through_the_guard_only_the_rows_of_the_reader_with_curations_that_the_browser_shows(): void
    {
        $this->allow(['view own articles']);
        $this->curateInternalsFirst();

        $this->assertSame(['Scout primer'], $this->guardedTitles(['filter_curated_hits' => false], $this->ada));
    }

    #[Test]
    public function it_refuses_through_the_guard_a_filter_that_does_not_terminate_or_pinned_hits(): void
    {
        $this->allow(['view own articles']);

        try {
            $this->guardedTitles(['filter_by' => 'id:*) || title:=`z'], $this->ada);
            $this->fail('The guard accepted a filter that does not terminate on its own.');
        } catch (RefusedSearchFilterException) {
        }

        $this->expectException(RefusedSearchParameterException::class);

        $this->guardedTitles(['pinned_hits' => '1:1'], $this->ada);
    }

    #[Test]
    public function it_finds_through_the_guard_nothing_for_a_closed_reader_and_everything_for_an_unrestricted_one(): void
    {
        $this->assertSame([], $this->guardedTitles(['filter_by' => 'featured:=true || featured:=false'], null));
        $this->assertSame([], $this->guardedTitles([], $this->ada));

        Gate::define('view global articles', fn (User $user): bool => true);
        Access::forgetDecisions();

        $this->assertSame(['Scout internals', 'Scout primer', 'Scout recipes'], $this->guardedTitles([], $this->ada));
    }
}

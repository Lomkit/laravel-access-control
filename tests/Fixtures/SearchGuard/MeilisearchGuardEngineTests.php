<?php

namespace Lomkit\Access\Tests\Fixtures\SearchGuard;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\RefusedSearchParameterException;
use Lomkit\Access\Search\Tokens\SearchTokenKey;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use PHPUnit\Framework\Attributes\Test;

trait MeilisearchGuardEngineTests
{
    private ?SearchTokenKey $guardedSearchKey = null;

    abstract protected function searchOnlyKey(string $index): SearchTokenKey;

    /**
     * @param  array<string, mixed>  $search
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    protected function guardedResponse(array $search, ?User $reader, array $fields = []): array
    {
        Access::discoverControlsIn([__DIR__]);
        $index = (new GuardedArticle)->searchableAs();
        $pagination = $fields === [] ? ['limit' => 100] : [];
        $body = Access::guardSearch([...$fields, 'queries' => [['indexUid' => $index, 'q' => 'Scout', ...$pagination, ...$search]]], $reader);

        return Http::baseUrl((string) getenv('MEILISEARCH_HOST'))
            ->withToken(($this->guardedSearchKey ??= $this->searchOnlyKey($index))->key())
            ->post('/multi-search', $body)
            ->throw()
            ->json();
    }

    /**
     * @param  array<string, mixed>  $search
     * @return list<string>
     */
    protected function guardedTitles(array $search, ?User $reader): array
    {
        $titles = array_map(fn (array $hit): string => $hit['title'], $this->guardedResponse($search, $reader)['results'][0]['hits']);
        sort($titles);

        return $titles;
    }

    #[Test]
    public function it_finds_through_the_guard_only_the_rows_of_the_reader_whatever_the_filter_of_the_browser(): void
    {
        $this->allow(['view own articles']);

        $this->assertSame(['Scout primer'], $this->guardedTitles([], $this->ada));
        $this->assertSame(['Scout primer'], $this->guardedTitles(['filter' => '_access EXISTS OR _access NOT EXISTS'], $this->ada));
        $this->assertSame(['Scout primer'], $this->guardedTitles(['filter' => [['_access EXISTS', '_access NOT EXISTS']]], $this->ada));

        try {
            $this->guardedTitles(['filter' => '_access EXISTS) OR (_access NOT EXISTS'], $this->ada);
            $this->fail('The engine accepted a filter that does not terminate on its own.');
        } catch (RequestException $exception) {
            $this->assertSame(400, $exception->response->status());
        }
    }

    #[Test]
    public function it_counts_through_the_guard_the_facets_of_the_rows_of_the_reader_only(): void
    {
        $this->allow(['view own articles']);

        $search = $this->guardedResponse(['facets' => ['_access']], $this->ada)['results'][0];
        $federated = $this->guardedResponse([], $this->ada, ['federation' => ['facetsByIndex' => [(new GuardedArticle)->searchableAs() => ['_access']]]]);

        $this->assertSame(1, array_sum($search['facetDistribution']['_access']));
        $this->assertSame(1, array_sum($federated['facetsByIndex'][(new GuardedArticle)->searchableAs()]['distribution']['_access']));
    }

    #[Test]
    public function it_refuses_through_the_guard_an_inline_rule(): void
    {
        $this->expectException(RefusedSearchParameterException::class);

        $this->guardedTitles(['inlineRule' => ['uid' => 'rule', 'actions' => ['pin' => [['id' => '1', 'position' => 0]]]]], $this->ada);
    }

    #[Test]
    public function it_finds_through_the_guard_nothing_for_a_closed_reader_and_everything_for_an_unrestricted_one(): void
    {
        $this->assertSame([], $this->guardedTitles(['filter' => '_access EXISTS OR _access NOT EXISTS'], null));
        $this->assertSame([], $this->guardedTitles([], $this->ada));

        Gate::define('view global articles', fn (User $user): bool => true);
        Access::forgetDecisions();

        $this->assertSame(['Scout internals', 'Scout primer', 'Scout recipes'], $this->guardedTitles([], $this->ada));
    }
}

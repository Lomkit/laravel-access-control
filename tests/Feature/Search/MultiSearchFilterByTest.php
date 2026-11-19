<?php

namespace Lomkit\Access\Tests\Feature\Search;

use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\TypesenseEngine;
use Lomkit\Access\Access;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Engines\MultiSearchTypesenseEngine;
use Lomkit\Access\Tests\Fixtures\Engines\RecordingHttpClient;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Typesense\Client;

#[Group('scout')]
#[Group('typesense')]
class MultiSearchFilterByTest extends SearchTestCase
{
    protected User $ada;

    protected RecordingHttpClient $http;

    #[\Override]
    protected function setUp(): void
    {
        if (! class_exists(Client::class) || ! method_exists(TypesenseEngine::class, 'executeSearch')) {
            $this->markTestSkipped('The Typesense client and TypesenseEngine::executeSearch() are needed.');
        }

        parent::setUp();

        $http = $this->http = new RecordingHttpClient;
        $engine = new MultiSearchTypesenseEngine(new Client([
            'api_key' => 'test-key',
            'nodes' => [['host' => 'typesense.test', 'port' => '8108', 'protocol' => 'http']],
            'client' => $http,
            'num_retries' => 0,
        ]), 1000);
        $this->app->make(EngineManager::class)->extend('typesense-multi-search', fn (): MultiSearchTypesenseEngine => $engine);
        config(['scout.driver' => 'typesense-multi-search']);
        $this->ada = $this->user('Ada');
        $this->allow(['view own articles']);
    }

    protected function accessFilter(): string
    {
        $clauses = [];

        foreach (Access::controlFor(Article::class)->searchFilter($this->ada)->whereIns() as $field => $values) {
            $clauses[] = sprintf('%s:=[%s]', $field, implode(', ', array_map(fn (string $value): string => "`{$value}`", $values)));
        }

        return implode(' && ', $clauses);
    }

    /** @return array<string, mixed> */
    protected function onlySearch(): array
    {
        $searches = $this->http->multiSearches();

        $this->assertCount(1, $searches);

        return $searches[0];
    }

    #[Test]
    public function it_sends_the_combined_filter_and_the_curated_hits_flag_through_the_multi_search_recipe(): void
    {
        Article::search('Scout')
            ->controlledFor($this->ada)
            ->options(['filter_by' => 'featured:=true', 'filter_curated_hits' => false])
            ->get();

        $search = $this->onlySearch();
        $this->assertSame("(featured:=true) && ({$this->accessFilter()})", $search['filter_by']);
        $this->assertTrue($search['filter_curated_hits']);
        $this->assertSame((new Article)->searchableAs(), $search['collection']);
    }

    #[Test]
    public function it_sends_the_access_filter_alone_through_the_multi_search_recipe_without_an_app_filter(): void
    {
        Article::search('Scout')
            ->controlledFor($this->ada)
            ->paginate(10);

        $search = $this->onlySearch();
        $this->assertSame($this->accessFilter(), $search['filter_by']);
        $this->assertTrue($search['filter_curated_hits']);
    }
}

<?php

namespace Lomkit\Access\Tests\Feature\Search;

use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Algolia4Engine;
use Lomkit\Access\Access;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Search\AlgoliaRequestEngine;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\MeilisearchRequestEngine;
use Lomkit\Access\Tests\Fixtures\Search\RecordingSearchClient;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineFilterOptionsTest extends SearchTestCase
{
    protected User $ada;

    protected RecordingSearchClient $client;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new RecordingSearchClient;
        $this->ada = $this->user('Ada');
        $this->allow(['view own articles', 'view featured articles']);
    }

    protected function useMeilisearch(): void
    {
        $engine = new MeilisearchRequestEngine($this->client);
        $this->app->make(EngineManager::class)->extend('meilisearch-requests', fn (): MeilisearchRequestEngine => $engine);
        config(['scout.driver' => 'meilisearch-requests']);
    }

    protected function useAlgolia(): void
    {
        if (! class_exists(Algolia4Engine::class)) {
            $this->markTestSkipped('This Scout version has no Algolia4Engine.');
        }

        $engine = new AlgoliaRequestEngine($this->client);
        $this->app->make(EngineManager::class)->extend('algolia-requests', fn (): AlgoliaRequestEngine => $engine);
        config(['scout.driver' => 'algolia-requests']);
    }

    /** @return array<string, list<string>> */
    protected function whereIns(): array
    {
        return Access::controlFor(Article::class)->searchFilter($this->ada)->whereIns();
    }

    protected function meilisearchAccess(): string
    {
        $clauses = [];

        foreach ($this->whereIns() as $field => $values) {
            $clauses[] = sprintf('%s IN [%s]', $field, implode(', ', array_map(fn (string $value): string => "\"{$value}\"", $values)));
        }

        return implode(' AND ', $clauses);
    }

    protected function algoliaAccess(): string
    {
        $clauses = [];

        foreach ($this->whereIns() as $field => $values) {
            $clauses[] = '('.implode(' OR ', array_map(fn (string $value): string => "{$field}:'{$value}'", $values)).')';
        }

        return implode(' AND ', $clauses);
    }

    /** @param  array<string, mixed>  $options */
    protected function searchEveryWay(array $options): void
    {
        Article::search('Scout')
            ->options($options)
            ->controlledFor($this->ada)
            ->get();
        Article::search('Scout')
            ->controlledFor($this->ada)
            ->options($options)
            ->get();
        Article::search('Scout')
            ->controlledFor($this->ada)
            ->options($options)
            ->paginate(10);
        Article::search('Scout')
            ->controlledFor($this->ada)
            ->options($options)
            ->keys();
    }

    #[Test]
    public function it_keeps_the_access_filter_of_meilisearch_over_the_filter_of_the_app(): void
    {
        $this->useMeilisearch();

        $this->searchEveryWay(['filter' => 'featured = true OR author_id EXISTS']);

        $this->assertCount(4, $this->client->requests);

        foreach ($this->client->requests as $request) {
            $this->assertSame($this->meilisearchAccess(), $request['filter']);
        }
    }

    #[Test]
    public function it_keeps_the_access_filter_of_algolia_over_the_filters_of_the_app(): void
    {
        $this->useAlgolia();

        $this->searchEveryWay(['filters' => 'featured:true OR NOT featured:true']);

        $this->assertCount(4, $this->client->requests);

        foreach ($this->client->requests as $request) {
            $this->assertSame($this->algoliaAccess(), $request['filters']);
        }
    }

    #[Test]
    public function it_sends_the_facet_and_numeric_filters_of_the_app_next_to_the_access_filter_of_algolia(): void
    {
        $this->useAlgolia();

        $this->searchEveryWay(['facetFilters' => [['featured:true', 'featured:false']], 'numericFilters' => ['author_id >= 0']]);

        $this->assertCount(4, $this->client->requests);

        foreach ($this->client->requests as $request) {
            $this->assertSame($this->algoliaAccess(), $request['filters']);
            $this->assertSame([['featured:true', 'featured:false']], $request['facetFilters']);
            $this->assertSame(['author_id >= 0'], $request['numericFilters']);
        }
    }
}

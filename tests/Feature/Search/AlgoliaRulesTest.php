<?php

namespace Lomkit\Access\Tests\Feature\Search;

use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Algolia4Engine;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Search\AlgoliaRequestEngine;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\RecordingSearchClient;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class AlgoliaRulesTest extends SearchTestCase
{
    protected User $ada;

    protected RecordingSearchClient $client;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(Algolia4Engine::class)) {
            $this->markTestSkipped('This Scout version has no Algolia4Engine.');
        }

        $this->client = new RecordingSearchClient;
        $engine = new AlgoliaRequestEngine($this->client);
        $this->app->make(EngineManager::class)->extend('algolia-requests', fn (): AlgoliaRequestEngine => $engine);
        config(['scout.driver' => 'algolia-requests']);
        $this->ada = $this->user('Ada');
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
        Article::search('Scout', fn (mixed $client, string $query, array $parameters): mixed => $client->searchSingleIndex('articles', $parameters))
            ->controlledFor($this->ada)
            ->options($options)
            ->raw();
    }

    #[Test]
    public function it_turns_the_rules_and_the_ab_tests_off_on_a_controlled_search_whatever_the_options(): void
    {
        $this->allow(['view own articles']);

        $this->searchEveryWay(['enableRules' => true, 'enableABTest' => true]);
        $this->searchEveryWay([]);

        $this->assertCount(10, $this->client->requests);

        foreach ($this->client->requests as $request) {
            $this->assertFalse($request['enableRules']);
            $this->assertFalse($request['enableABTest']);
        }
    }

    #[Test]
    public function it_leaves_the_rules_of_an_unrestricted_reader_as_the_options_say(): void
    {
        $this->allow(['view global articles']);

        $this->searchEveryWay(['enableRules' => true]);

        foreach ($this->client->requests as $request) {
            $this->assertTrue($request['enableRules']);
        }
    }
}

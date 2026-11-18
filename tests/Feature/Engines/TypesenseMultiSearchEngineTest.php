<?php

namespace Lomkit\Access\Tests\Feature\Engines;

use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\TypesenseEngine;
use Lomkit\Access\Tests\Fixtures\Engines\MultiSearchTypesenseEngine;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Typesense\Client;

#[Group('scout')]
#[Group('typesense')]
class TypesenseMultiSearchEngineTest extends TypesenseEngineTest
{
    #[\Override]
    protected function setUp(): void
    {
        if (! method_exists(TypesenseEngine::class, 'executeSearch')) {
            $this->markTestSkipped('This Scout version has no TypesenseEngine::executeSearch().');
        }

        parent::setUp();
    }

    #[\Override]
    protected function configureDriver($app): void
    {
        parent::configureDriver($app);

        $app->resolving(EngineManager::class, function (EngineManager $engines): void {
            $engines->extend('typesense', fn (): MultiSearchTypesenseEngine => new MultiSearchTypesenseEngine(
                new Client(config('scout.typesense.client-settings')),
                1000,
                config('scout.typesense'),
            ));
        });
    }

    #[\Override]
    protected function assertLongFilterIsHandled(): void
    {
        $this->assertSame(['Scout primer'], $this->searchedTitles());
    }

    #[Test]
    public function it_searches_through_the_multi_search_subclass(): void
    {
        $this->allow(['view own articles']);

        $this->assertInstanceOf(MultiSearchTypesenseEngine::class, (new Article)->searchableUsing());
        $this->assertSame(['Scout primer'], $this->searchedTitles());
    }
}

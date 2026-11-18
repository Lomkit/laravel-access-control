<?php

namespace Lomkit\Access\Tests\Feature\Engines;

use Laravel\Scout\EngineManager;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\AuthorPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\MultiSearchTypesenseEngine;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Typesense\Client;

#[Group('scout')]
#[Group('typesense')]
class TypesenseMultiSearchEngineTest extends TypesenseEngineTest
{
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

    #[Test]
    public function it_searches_through_the_multi_search_subclass(): void
    {
        $this->allow(['view own articles']);

        $this->assertInstanceOf(MultiSearchTypesenseEngine::class, (new Article)->searchableUsing());
        $this->assertSame(['Scout primer'], $this->searchedTitles());
    }

    #[Test]
    public function it_searches_with_more_keys_than_a_query_string_of_typesense_holds(): void
    {
        ConfiguredArticleControl::bind(
            AuthorPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys('author', fn (Article $article): mixed => $article->author_id, fn (User $user): array => [...range(100000, 100199), $user->getKey()]),
        );
        Article::query()->get()->searchable();
        $this->waitForDocuments(3);

        $this->assertSame(['Scout primer'], $this->searchedTitles());
    }
}

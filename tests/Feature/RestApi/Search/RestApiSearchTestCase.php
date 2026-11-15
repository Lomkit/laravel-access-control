<?php

namespace Lomkit\Access\Tests\Feature\RestApi\Search;

use Illuminate\Testing\TestResponse;
use Laravel\Scout\EngineManager;
use Lomkit\Access\Access;
use Lomkit\Access\Tests\Feature\Search\Parents\ParentSearchTestCase;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\RestApi\Search\IndexedMeilisearchEngine;
use Lomkit\Access\Tests\Fixtures\RestApi\Search\RestApiSearch;
use Lomkit\Rest\RestServiceProvider;

abstract class RestApiSearchTestCase extends ParentSearchTestCase
{
    protected IndexedMeilisearchEngine $index;

    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [RestServiceProvider::class, ...parent::getPackageProviders($app)];
    }

    #[\Override]
    protected function defineRoutes($router): void
    {
        RestApiSearch::routes();
    }

    protected function defineSnippetRoutes(): void
    {
        RestApiSearch::snippetRoutes();
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Access::registerPolicies();
        $index = $this->index = new IndexedMeilisearchEngine($this->engine);
        $manager = $this->app->make(EngineManager::class);
        $manager->extend('memory', fn (): IndexedMeilisearchEngine => $index);
        $manager->forgetDrivers();
    }

    protected function textSearch(string $resource, ?User $reader, string $text): TestResponse
    {
        if ($reader !== null) {
            $this->actingAs($reader);
        }

        return $this->postJson("/{$resource}/search", ['search' => ['text' => ['value' => $text]]]);
    }

    /** @return list<string> */
    protected function found(string $resource, ?User $reader, string $text, string $field): array
    {
        $values = array_column($this->textSearch($resource, $reader, $text)->assertOk()->json('data'), $field);
        sort($values);

        return $values;
    }
}

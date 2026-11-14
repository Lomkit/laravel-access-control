<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Illuminate\Support\Facades\Gate;
use Laravel\Scout\EngineManager;
use Laravel\Scout\ScoutServiceProvider;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Testing\AccessTestCase;
use Orchestra\Testbench\Attributes\WithConfig;

#[WithConfig('scout.driver', 'memory')]
#[WithConfig('scout.queue', false)]
abstract class SearchTestCase extends AccessTestCase
{
    protected MemoryEngine $engine;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog, new Search];
    }

    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            ScoutServiceProvider::class,
        ];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $engine = $this->engine = new MemoryEngine;
        $this->app->make(EngineManager::class)->extend('memory', fn (): MemoryEngine => $engine);
    }

    protected function user(string $name, ?Client $client = null, ?int $workspace = 1): User
    {
        return User::factory()
            ->for($client ?? Client::factory()->createOne(['name' => "{$name} Inc"]))
            ->createOne(['name' => $name, 'email' => strtolower($name).'@example.com', 'current_workspace_id' => $workspace]);
    }

    /** @param  list<string>  $abilities */
    protected function allow(array $abilities): void
    {
        foreach ($abilities as $ability) {
            Gate::define($ability, fn (User $user): bool => true);
        }
    }

    protected function reindex(): void
    {
        Article::query()->get()->searchable();
    }

    /** @return array<string, mixed> */
    protected function document(Article $article): array
    {
        return $this->engine->documents[$article->searchableAs()][(string) $article->getKey()];
    }

    /** @return list<string> */
    protected function searchedTitles(string $query = 'Scout'): array
    {
        return Article::search($query)
            ->controlled()
            ->get()
            ->pluck('title')
            ->sort()
            ->values()
            ->all();
    }
}

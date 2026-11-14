<?php

namespace Lomkit\Access\Tests\Feature\Engines;

use Lomkit\Access\Access;
use Lomkit\Access\Search\Tokens\SearchToken;
use Lomkit\Access\Search\Tokens\SearchTokenKey;
use Lomkit\Access\Tests\Fixtures\Search\EngineTestCase;
use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
use Meilisearch\Exceptions\ApiException;
use PHPUnit\Framework\Attributes\Group;

#[Group('scout')]
#[Group('meilisearch')]
class MeilisearchEngineTest extends EngineTestCase
{
    #[\Override]
    protected function driver(): string
    {
        return 'meilisearch';
    }

    #[\Override]
    protected function hostVariable(): string
    {
        return 'MEILISEARCH_HOST';
    }

    #[\Override]
    protected function configureDriver($app): void
    {
        $app['config']->set('scout.meilisearch.host', getenv('MEILISEARCH_HOST'));
        $app['config']->set('scout.meilisearch.key', getenv('MEILISEARCH_KEY') ?: null);
    }

    #[\Override]
    protected function prepareIndex(string $index): void
    {
        $client = $this->app->make(Client::class);

        $task = $client->index($index)->updateFilterableAttributes(Access::searchFilterables());

        $client->waitForTask($task['taskUid']);
    }

    #[\Override]
    protected function waitForIndexing(string $index): void
    {
        $client = $this->app->make(Client::class);
        $pending = $client->getTasks((new TasksQuery)->setIndexUids([$index])->setStatuses(['enqueued', 'processing']));
        $uids = array_map(fn (array $task): int => $task['uid'], $pending->getResults());

        if ($uids !== []) {
            $client->waitForTasks($uids);
        }
    }

    #[\Override]
    protected function searchOnlyKey(string $index): SearchTokenKey
    {
        $client = $this->app->make(Client::class);
        $key = $client->createKey(['description' => 'Search-only key of a test', 'actions' => ['search'], 'indexes' => [$index], 'expiresAt' => null]);
        $this->beforeApplicationDestroyed(fn () => $client->deleteKey($key->getUid() ?? ''));

        return SearchTokenKey::meilisearch(uid: $key->getUid() ?? '', key: $key->getKey() ?? '');
    }

    #[\Override]
    protected function titlesFoundWith(SearchToken $token, string $index): array
    {
        $hits = (new Client((string) getenv('MEILISEARCH_HOST'), $token->token()))
            ->index($index)
            ->search('Scout', ['limit' => 100])
            ->getHits();

        return array_values(array_map(fn (array $hit): string => $hit['title'], $hits));
    }

    #[\Override]
    protected function refusedSearch(): string
    {
        return ApiException::class;
    }
}

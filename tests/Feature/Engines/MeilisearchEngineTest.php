<?php

namespace Lomkit\Access\Tests\Feature\Engines;

use Lomkit\Access\Access;
use Lomkit\Access\Tests\Fixtures\Search\EngineTestCase;
use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
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
}

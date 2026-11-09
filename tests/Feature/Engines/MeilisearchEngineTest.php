<?php

namespace Lomkit\Access\Tests\Feature\Engines;

use Meilisearch\Client;
use PHPUnit\Framework\Attributes\Group;

#[Group('scout')]
#[Group('meilisearch')]
class MeilisearchEngineTest extends ScoutEngineTestCase
{
    protected function driver(): string
    {
        return 'meilisearch';
    }

    protected function hostVariable(): string
    {
        return 'MEILISEARCH_HOST';
    }

    protected function configureDriver($app): void
    {
        $app['config']->set('scout.meilisearch.host', getenv('MEILISEARCH_HOST'));
        $app['config']->set('scout.meilisearch.key', getenv('MEILISEARCH_KEY') ?: null);
    }

    protected function prepareIndex(string $index): void
    {
        $client = app(Client::class);

        $task = $client->index($index)->updateFilterableAttributes(['client_id']);

        $client->waitForTask($task['taskUid']);
    }
}

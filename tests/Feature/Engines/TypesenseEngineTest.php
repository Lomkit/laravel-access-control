<?php

namespace Lomkit\Access\Tests\Feature\Engines;

use Lomkit\Access\Tests\Support\Models\SearchableModel;
use PHPUnit\Framework\Attributes\Group;

#[Group('scout')]
#[Group('typesense')]
class TypesenseEngineTest extends ScoutEngineTestCase
{
    protected function driver(): string
    {
        return 'typesense';
    }

    protected function hostVariable(): string
    {
        return 'TYPESENSE_HOST';
    }

    protected function configureDriver($app): void
    {
        $app['config']->set('scout.typesense.client-settings', [
            'api_key' => getenv('TYPESENSE_API_KEY'),
            'nodes'   => [[
                'host'     => getenv('TYPESENSE_HOST'),
                'port'     => getenv('TYPESENSE_PORT') ?: '8108',
                'protocol' => getenv('TYPESENSE_PROTOCOL') ?: 'http',
            ]],
            'connection_timeout_seconds' => 2,
            'num_retries'                => 0,
        ]);
        $app['config']->set('scout.typesense.model-settings', [
            SearchableModel::class => [
                'collection-schema' => [
                    'fields' => [
                        ['name' => 'id', 'type' => 'string'],
                        ['name' => 'name', 'type' => 'string'],
                        ['name' => 'client_id', 'type' => 'int64', 'optional' => true],
                    ],
                ],
                'search-parameters' => ['query_by' => 'name'],
            ],
        ]);
    }
}

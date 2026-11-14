<?php

namespace Lomkit\Access\Tests\Feature\Engines;

use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\EngineTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('scout')]
#[Group('typesense')]
class TypesenseEngineTest extends EngineTestCase
{
    #[\Override]
    protected function driver(): string
    {
        return 'typesense';
    }

    #[\Override]
    protected function hostVariable(): string
    {
        return 'TYPESENSE_HOST';
    }

    #[\Override]
    protected function configureDriver($app): void
    {
        $app['config']->set('scout.typesense.client-settings', [
            'api_key' => getenv('TYPESENSE_API_KEY'),
            'nodes' => [[
                'host' => getenv('TYPESENSE_HOST'),
                'port' => getenv('TYPESENSE_PORT') ?: '8108',
                'protocol' => getenv('TYPESENSE_PROTOCOL') ?: 'http',
            ]],
            'connection_timeout_seconds' => 2,
            'num_retries' => 0,
        ]);
        $app['config']->set('scout.typesense.model-settings', [
            Article::class => [
                'collection-schema' => [
                    'fields' => [
                        ['name' => 'id', 'type' => 'string'],
                        ['name' => 'title', 'type' => 'string'],
                        ['name' => 'author_id', 'type' => 'int64', 'optional' => true],
                        ['name' => 'featured', 'type' => 'bool'],
                        ['name' => '_access', 'type' => 'string[]'],
                        ['name' => '_boundary', 'type' => 'string[]'],
                        ['name' => '_access_version', 'type' => 'string'],
                    ],
                ],
                'search-parameters' => ['query_by' => 'title'],
            ],
        ]);
    }
}

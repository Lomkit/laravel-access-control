<?php

namespace Lomkit\Access\Tests\Feature\Engines;

use Lomkit\Access\Search\Tokens\SearchToken;
use Lomkit\Access\Search\Tokens\SearchTokenKey;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\EngineTestCase;
use PHPUnit\Framework\Attributes\Group;
use Typesense\Client;
use Typesense\Exceptions\RequestUnauthorized;

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
                    ],
                ],
                'search-parameters' => ['query_by' => 'title'],
            ],
        ]);
    }

    /** @return array<string, mixed> */
    protected function clientSettings(string $key): array
    {
        return [...config('scout.typesense.client-settings'), 'api_key' => $key];
    }

    #[\Override]
    protected function searchOnlyKey(string $index): SearchTokenKey
    {
        $client = new Client($this->clientSettings((string) getenv('TYPESENSE_API_KEY')));
        $key = $client->keys->create(['description' => 'Search-only key of a test', 'actions' => ['documents:search'], 'collections' => [$index]]);
        $this->beforeApplicationDestroyed(fn () => $client->keys[$key['id']]->delete());

        return SearchTokenKey::typesense($key['value']);
    }

    #[\Override]
    protected function titlesFoundWith(SearchToken $token, string $index): array
    {
        $results = (new Client($this->clientSettings($token->token())))
            ->collections[$index]
            ->documents
            ->search(['q' => 'Scout', 'query_by' => 'title', 'per_page' => 100]);

        return array_values(array_map(fn (array $hit): string => $hit['document']['title'], $results['hits']));
    }

    #[\Override]
    protected function refusedSearch(): string
    {
        return RequestUnauthorized::class;
    }
}

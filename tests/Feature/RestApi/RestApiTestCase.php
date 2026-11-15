<?php

namespace Lomkit\Access\Tests\Feature\RestApi;

use Illuminate\Testing\TestResponse;
use Lomkit\Access\Tests\Feature\Parents\ParentTestCase;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\RestApi\RestApi;
use Lomkit\Rest\RestServiceProvider;

abstract class RestApiTestCase extends ParentTestCase
{
    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [RestServiceProvider::class, ...parent::getPackageProviders($app)];
    }

    #[\Override]
    protected function defineRoutes($router): void
    {
        RestApi::routes();
    }

    /** @param  array<string, mixed>  $search */
    protected function search(string $resource, ?User $reader, array $search = []): TestResponse
    {
        if ($reader !== null) {
            $this->actingAs($reader);
        }

        return $this->postJson("/{$resource}/search", ['search' => $search + ['sorts' => [['field' => 'id']]]]);
    }

    /**
     * @param  array<string, mixed>  $search
     * @return list<mixed>
     */
    protected function searched(string $resource, ?User $reader, string $field, array $search = []): array
    {
        $response = $this->search($resource, $reader, $search);
        $response->assertOk();

        return array_column($response->json('data'), $field);
    }
}

<?php

namespace Lomkit\Access\Tests\Feature\RestApi\Search;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\TurbopufferEngine;
use Lomkit\Access\Search\SearchField;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\RestApi\Search\ThreadResource;
use Lomkit\Access\Tests\Fixtures\Threads\Reply;
use Lomkit\Access\Tests\Fixtures\Threads\Thread;
use Lomkit\Rest\Facades\Rest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('rest-api')]
#[Group('scout')]
class TurbopufferSearchTest extends RestApiSearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'scout.driver' => 'turbopuffer',
            'scout.turbopuffer' => [
                'api_key' => 'test-key',
                'region' => 'gcp-us-central1',
                'timeout' => 5,
                'connect_timeout' => 5,
                'retries' => 0,
                'model-settings' => [Thread::class => ['searchable-attributes' => ['title']], Reply::class => ['searchable-attributes' => ['body']]],
            ],
        ]);
        $this->app->make(EngineManager::class)->forgetDrivers();
        Http::preventStrayRequests();
        Http::fake(fn (Request $request): PromiseInterface => $this->respond($request));
        $this->assertInstanceOf(TurbopufferEngine::class, (new Thread)->searchableUsing());
    }

    #[Test]
    public function it_sends_the_access_keys_of_the_reader_as_contains_any_filters(): void
    {
        $this->grant(['view client threads' => ['Ada']]);

        $this->assertSame(['Roadmap'], $this->found('threads', $this->ada, 'roadmap', 'title'));

        $filters = ['And', [
            [SearchField::Boundary->value, 'ContainsAny', ['workspace_id.1']],
            [SearchField::Access->value, 'ContainsAny', ['client.'.$this->acme->getKey()]],
        ]];
        $this->assertSame([
            ['rank_by' => ['title', 'BM25', 'roadmap'], 'filters' => $filters, 'limit' => 50],
            ['aggregate_by' => ['count' => ['Count']], 'filters' => $filters],
        ], $this->queries());
    }

    #[Test]
    public function it_sends_the_composite_keys_of_a_child_as_one_contains_any_filter(): void
    {
        $this->grant(['view client threads' => ['Ada'], 'view own replies' => ['Ada']]);

        $this->textSearch('replies', $this->ada, 'ship')->assertOk();

        $this->assertSame(
            [SearchField::Access->value, 'ContainsAny', ['through.thread.2.workspace_id.1.client.'.$this->acme->getKey(), 'author.'.$this->ada->getKey()]],
            $this->queries((new Reply)->searchableAs())[0]['filters'],
        );
    }

    #[Test]
    public function it_sends_no_request_for_a_closed_reader(): void
    {
        Gate::before(fn (?User $user): bool => true);

        $this->assertSame([], Rest::search(ThreadResource::class)->as(null)->payload(['text' => ['value' => 'roadmap']])->page()->items());
        $this->assertSame([], $this->queries());
    }

    /** @return list<array<string, mixed>> */
    private function queries(?string $namespace = null): array
    {
        $queries = [];
        $namespace ??= (new Thread)->searchableAs();

        foreach (Http::recorded() as [$request]) {
            if (str_ends_with($request->url(), "/namespaces/{$namespace}/query")) {
                $queries[] = $request->data();
            }
        }

        return $queries;
    }

    private function respond(Request $request): PromiseInterface
    {
        if (! str_ends_with($request->url(), '/query')) {
            return Http::response(['status' => 'OK', 'rows_affected' => 1]);
        }

        $usage = ['billing' => ['billable_logical_bytes_queried' => 0, 'billable_logical_bytes_returned' => 0], 'performance' => ['server_total_ms' => 1]];

        if (isset($request->data()['aggregate_by'])) {
            return Http::response(['aggregations' => ['count' => 1], ...$usage]);
        }

        return Http::response(['rows' => [['$dist' => 1.5, 'id' => (string) $this->roadmap->getKey()]], ...$usage]);
    }
}

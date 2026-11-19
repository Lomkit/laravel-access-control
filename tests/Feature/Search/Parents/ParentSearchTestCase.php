<?php

namespace Lomkit\Access\Tests\Feature\Search\Parents;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Access;
use Lomkit\Access\Search\SearchDocuments;
use Lomkit\Access\Search\SearchKeyDigest;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use Lomkit\Access\Tests\Fixtures\Threads\Forum;
use Lomkit\Access\Tests\Fixtures\Threads\Reply;
use Lomkit\Access\Tests\Fixtures\Threads\Thread;
use Lomkit\Access\Tests\Fixtures\Threads\Threads;

abstract class ParentSearchTestCase extends SearchTestCase
{
    protected Client $acme;

    protected Client $globex;

    protected User $ada;

    protected User $grace;

    protected User $linus;

    protected User $root;

    protected Forum $general;

    protected Forum $support;

    protected Thread $roadmap;

    protected Thread $pricing;

    protected Thread $offsite;

    protected Thread $lost;

    #[\Override]
    protected function fixtures(): array
    {
        return [...parent::fixtures(), new Threads];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        File::delete([$this->app->bootstrapPath('cache/access-controls.php'), $this->app->bootstrapPath('cache/access-reindex.php')]);
        Access::discoverControlsIn([dirname(__DIR__, 3).'/Fixtures/Threads/Controls']);

        $this->acme = Client::factory()->createOne(['name' => 'Acme']);
        $this->globex = Client::factory()->createOne(['name' => 'Globex']);
        $this->ada = $this->user('Ada', $this->acme);
        $this->grace = $this->user('Grace', $this->acme);
        $this->linus = $this->user('Linus', $this->globex);
        $this->root = $this->user('Root', $this->globex, 2);
        $this->general = Forum::query()->create(['client_id' => $this->acme->getKey(), 'name' => 'General']);
        $this->support = Forum::query()->create(['client_id' => $this->globex->getKey(), 'name' => 'Support']);
        $this->roadmap = $this->thread($this->general, $this->ada, 'Roadmap', 1);
        $this->pricing = $this->thread($this->support, $this->linus, 'Pricing', 1);
        $this->offsite = $this->thread($this->general, $this->grace, 'Offsite', 2);
        $this->lost = $this->thread($this->general, $this->ada, 'Lost', null);
        $this->reply($this->roadmap, $this->grace, 'Ship it');
        $this->reply($this->roadmap, $this->linus, 'Typo in the title');
        $this->reply($this->pricing, $this->ada, 'Too expensive');
        $this->reply($this->offsite, $this->ada, 'Wrong workspace');
        $this->reply($this->lost, $this->grace, 'Nowhere');
    }

    protected function thread(Forum $forum, User $author, string $title, ?int $workspace): Thread
    {
        return Thread::query()->create(['forum_id' => $forum->getKey(), 'author_id' => $author->getKey(), 'title' => $title, 'workspace_id' => $workspace]);
    }

    protected function reply(Thread $thread, User $author, string $body): Reply
    {
        return Reply::query()->create(['thread_id' => $thread->getKey(), 'author_id' => $author->getKey(), 'body' => $body]);
    }

    protected function replyNamed(string $body): Reply
    {
        return Reply::query()->where('body', $body)->firstOrFail();
    }

    /** @param  array<string, list<string>>  $permissions */
    protected function grant(array $permissions): void
    {
        foreach ($permissions as $permission => $names) {
            Gate::define($permission, fn (User $reader): bool => in_array($reader->getAttribute('name'), $names, true));
        }
    }

    /** @return list<string> */
    protected function accessKeysOf(string $body): array
    {
        $reply = $this->replyNamed($body);
        $keys = $this->engine->documents[$reply->searchableAs()][(string) $reply->getKey()]['_access'] ?? [];
        $this->assertIsArray($keys);
        $keys = array_values(array_filter($keys, is_string(...)));
        sort($keys);

        return $keys;
    }

    /**
     * @param  list<string>  $readable
     * @return list<string>
     */
    protected function digestsOf(array $readable): array
    {
        $digests = SearchKeyDigest::all($readable);
        sort($digests);

        return $digests;
    }

    /** @return list<string> */
    protected function readableKeysOf(string $body): array
    {
        $rules = Access::controlFor(Reply::class)->searchRules();
        $keys = (new SearchDocuments($rules))->keysFor([$this->replyNamed($body)])[0]['_access'];
        sort($keys);

        return $keys;
    }

    /** @param  list<string>  $readable */
    protected function assertRowKeys(array $readable, string $body): void
    {
        $this->assertSame($readable, $this->readableKeysOf($body));
        $this->assertSame($this->digestsOf($readable), $this->accessKeysOf($body));
    }

    /** @return list<string> */
    protected function searchedReplies(?User $reader): array
    {
        return Reply::search('')
            ->controlledFor($reader)
            ->get()
            ->pluck('body')
            ->sort()
            ->values()
            ->all();
    }

    protected function reindexReplies(): void
    {
        Reply::query()->get()->searchable();
    }
}

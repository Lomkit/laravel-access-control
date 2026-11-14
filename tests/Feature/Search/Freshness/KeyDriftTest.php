<?php

namespace Lomkit\Access\Tests\Feature\Search\Freshness;

use Illuminate\Support\Facades\DB;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredQuietArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\QuietArticle;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class KeyDriftTest extends SearchTestCase
{
    protected QuietArticle $article;

    protected User $ada;

    protected User $grace;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->ada = $this->user('Ada');
        $this->grace = $this->user('Grace');
        $this->article = QuietArticle::query()->create(['author_id' => $this->ada->getKey(), 'title' => 'Scout primer', 'body' => 'Index the rows.', 'featured' => false]);
        $this->article->searchable();
        $this->engine->updates = [];
    }

    protected function access(): mixed
    {
        return $this->engine->documents[$this->article->searchableAs()][(string) $this->article->getKey()]['_access'] ?? null;
    }

    #[Test]
    public function it_reindexes_a_change_of_a_key_attribute_that_the_model_tells_scout_to_skip(): void
    {
        $this->article->update(['author_id' => $this->grace->getKey()]);

        $this->assertSame(["author.{$this->grace->getKey()}"], $this->access());
    }

    #[Test]
    public function it_reindexes_a_key_that_disappears(): void
    {
        $this->article->update(['author_id' => null, 'featured' => true]);

        $this->assertSame(['featured.yes'], $this->access());
    }

    #[Test]
    public function it_leaves_the_document_alone_when_no_key_changes(): void
    {
        $this->article->update(['title' => 'Scout recipes']);

        $this->assertSame([], $this->engine->updates);
    }

    #[Test]
    public function it_reindexes_after_the_transaction_commits(): void
    {
        DB::transaction(function (): void {
            $this->article->update(['author_id' => $this->grace->getKey()]);

            $this->assertSame([], $this->engine->updates);
        });

        $this->assertSame(["author.{$this->grace->getKey()}"], $this->access());
    }

    #[Test]
    public function it_reindexes_nothing_when_the_transaction_rolls_back(): void
    {
        DB::beginTransaction();
        $this->article->update(['author_id' => $this->grace->getKey()]);
        DB::rollBack();

        $this->assertSame([], $this->engine->updates);
        $this->assertSame(["author.{$this->ada->getKey()}"], $this->access());
    }

    #[Test]
    public function it_respects_a_model_that_does_not_sync_to_the_search(): void
    {
        QuietArticle::withoutSyncingToSearch(function (): void {
            $this->article->update(['author_id' => $this->grace->getKey()]);
        });

        $this->assertSame([], $this->engine->updates);
    }

    #[Test]
    public function it_removes_a_document_that_should_no_longer_be_searchable_when_its_keys_change(): void
    {
        $this->article->update(['author_id' => $this->grace->getKey(), 'title' => 'Hidden']);

        $this->assertNull($this->access());
    }

    #[Test]
    public function it_removes_such_a_document_once_the_transaction_commits(): void
    {
        DB::transaction(function (): void {
            $this->article->update(['author_id' => $this->grace->getKey(), 'title' => 'Hidden']);
        });

        $this->assertNull($this->access());
    }

    #[Test]
    public function it_reindexes_a_key_read_through_a_relation_when_the_foreign_key_changes(): void
    {
        $acme = Client::factory()->createOne(['name' => 'Acme']);
        $globex = Client::factory()->createOne(['name' => 'Globex']);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $warehouse = Project::factory()->for($globex)->createOne(['name' => 'Warehouse']);
        ConfiguredQuietArticleControl::bindWithin(
            Boundary::where('workspace_id', fn (User $user): ?int => $user->current_workspace_id),
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys(
                    name: 'client',
                    row: fn (QuietArticle $article): mixed => $article->project?->client_id,
                    reader: fn (User $user): mixed => $user->client_id,
                    load: ['project:id,client_id'],
                ),
        );
        $this->article->update(['project_id' => $website->getKey(), 'workspace_id' => 1]);
        $this->article->searchable();
        $this->assertSame(["client.{$acme->getKey()}"], $this->access());

        $this->article->update(['project_id' => $warehouse->getKey(), 'workspace_id' => 2]);

        $this->assertSame(["client.{$globex->getKey()}"], $this->access());
        $this->assertSame(['workspace_id.2'], $this->engine->documents[$this->article->searchableAs()][(string) $this->article->getKey()]['_boundary']);
    }
}

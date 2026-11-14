<?php

namespace Lomkit\Access\Tests\Feature\Search\Freshness;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Lomkit\Access\Exceptions\DuplicateReindexException;
use Lomkit\Access\Exceptions\InvalidReindexRowsException;
use Lomkit\Access\Exceptions\MissingReindexDeclarationException;
use Lomkit\Access\Search\Freshness\ReindexWatchedRows;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\ArchiveFolder;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\ArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\Assignment;
use Lomkit\Access\Tests\Fixtures\Search\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\Folder;
use Lomkit\Access\Tests\Fixtures\Search\FreshnessTestCase;
use Lomkit\Access\Tests\Fixtures\Search\SharedPerimeter;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class ReindexWhenTest extends FreshnessTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->bindFolderKeys();
    }

    protected function bindAssigneeKeys(): void
    {
        ConfiguredArticleControl::bind(
            SharedPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(function (Builder $query, User $user): void {
                    $query->whereRelation('assignments', 'user_id', $user->getKey());
                })
                ->searchKeys(
                    name: 'assignee',
                    row: fn (Article $article): array => $article->assignments->pluck('user_id')->all(),
                    reader: fn (User $user): mixed => $user->getKey(),
                    load: ['assignments:id,article_id,user_id'],
                )
                ->reindexWhen(Assignment::class, changed: ['article_id', 'user_id'], rows: fn (Assignment $assignment) => Article::query()->whereKey($assignment->getAttribute('article_id'))),
        );
    }

    #[Test]
    public function it_reindexes_the_rows_when_a_watched_attribute_changes(): void
    {
        $folder = $this->folder($this->acme);
        $article = $this->article($folder);
        $this->assertSame(["client.{$this->acme->getKey()}"], $this->accessOf($article));

        $folder->update(['client_id' => $this->globex->getKey()]);

        $this->assertSame(["client.{$this->globex->getKey()}"], $this->accessOf($article));
    }

    #[Test]
    public function it_queues_nothing_when_an_unwatched_attribute_changes(): void
    {
        $folder = $this->folder($this->acme);
        $this->article($folder);
        Queue::fake();

        $folder->update(['name' => 'Playbook']);

        Queue::assertNotPushed(ReindexWatchedRows::class);
    }

    #[Test]
    public function it_queues_nothing_for_a_save_that_changes_nothing(): void
    {
        $folder = $this->folder($this->acme);
        Queue::fake();

        $folder->update(['client_id' => $this->acme->getKey()]);

        Queue::assertNotPushed(ReindexWatchedRows::class);
    }

    #[Test]
    public function it_reindexes_the_rows_of_a_watched_row_deleted_before_the_job_runs(): void
    {
        $folder = $this->folder($this->acme);
        $article = $this->article($folder);

        $folder->forceDelete();

        $this->assertSame([], $this->accessOf($article));
    }

    #[Test]
    public function it_reindexes_the_rows_when_a_watched_row_is_soft_deleted_and_restored(): void
    {
        $folder = $this->folder($this->acme);
        $article = $this->article($folder);

        $folder->delete();
        $this->assertSame([], $this->accessOf($article));

        $folder->restore();
        $this->assertSame(["client.{$this->acme->getKey()}"], $this->accessOf($article));
    }

    #[Test]
    public function it_reindexes_the_rows_that_a_created_watched_row_gives_keys_to(): void
    {
        $article = Article::factory()->createOne(['folder_id' => 99, 'title' => 'Scout primer']);
        $this->assertSame([], $this->accessOf($article));

        Folder::query()->create(['id' => 99, 'client_id' => $this->acme->getKey(), 'name' => 'Handbook']);

        $this->assertSame(["client.{$this->acme->getKey()}"], $this->accessOf($article));
    }

    #[Test]
    public function it_reindexes_the_rows_of_the_previous_state_of_the_watched_row(): void
    {
        $this->bindAssigneeKeys();
        $first = Article::factory()->createOne(['title' => 'Scout primer']);
        $second = Article::factory()->createOne(['title' => 'Scout recipes']);
        $assignment = Assignment::query()->create(['article_id' => $first->getKey(), 'user_id' => $this->ada->getKey()]);
        $this->assertSame(["assignee.{$this->ada->getKey()}"], $this->accessOf($first));

        $assignment->update(['article_id' => $second->getKey()]);

        $this->assertSame([], $this->accessOf($first));
        $this->assertSame(["assignee.{$this->ada->getKey()}"], $this->accessOf($second));
    }

    #[Test]
    public function it_reindexes_the_row_that_a_deleted_watched_row_pointed_to(): void
    {
        $this->bindAssigneeKeys();
        $article = Article::factory()->createOne(['title' => 'Scout primer']);
        $assignment = Assignment::query()->create(['article_id' => $article->getKey(), 'user_id' => $this->ada->getKey()]);

        $assignment->delete();

        $this->assertSame([], $this->accessOf($article));
    }

    #[Test]
    public function it_reindexes_after_the_transaction_commits(): void
    {
        $folder = $this->folder($this->acme);
        $article = $this->article($folder);

        DB::transaction(function () use ($folder, $article): void {
            $folder->update(['client_id' => $this->globex->getKey()]);

            $this->assertSame(["client.{$this->acme->getKey()}"], $this->accessOf($article));
        });

        $this->assertSame(["client.{$this->globex->getKey()}"], $this->accessOf($article));
    }

    #[Test]
    #[WithConfig('scout.after_commit', false)]
    public function it_reindexes_after_the_commit_whatever_the_after_commit_option_of_scout(): void
    {
        $folder = $this->folder($this->acme);
        $article = $this->article($folder);
        $updates = count($this->engine->updates);

        DB::transaction(function () use ($folder, $updates): void {
            $folder->update(['client_id' => $this->globex->getKey()]);

            $this->assertCount($updates, $this->engine->updates);
        });

        $this->assertSame(["client.{$this->globex->getKey()}"], $this->accessOf($article));
    }

    #[Test]
    public function it_reindexes_nothing_when_the_transaction_rolls_back(): void
    {
        $folder = $this->folder($this->acme);
        $article = $this->article($folder);
        $updates = count($this->engine->updates);

        DB::beginTransaction();
        $folder->update(['client_id' => $this->globex->getKey()]);
        DB::rollBack();

        $this->assertCount($updates, $this->engine->updates);
        $this->assertSame(["client.{$this->acme->getKey()}"], $this->accessOf($article));

        $folder->refresh()->update(['client_id' => $this->globex->getKey()]);

        $this->assertSame(["client.{$this->globex->getKey()}"], $this->accessOf($article));
    }

    #[Test]
    #[WithConfig('scout.chunk.searchable', 2)]
    public function it_reindexes_the_rows_in_chunks(): void
    {
        $folder = $this->folder($this->acme);
        $articles = collect(range(1, 5))->map(fn (int $number): Article => $this->article($folder, "Scout part {$number}"));
        $this->engine->updates = [];

        $folder->update(['client_id' => $this->globex->getKey()]);

        $this->assertSame(
            [[(string) $articles[0]->getKey(), (string) $articles[1]->getKey()], [(string) $articles[2]->getKey(), (string) $articles[3]->getKey()], [(string) $articles[4]->getKey()]],
            $this->engine->updates,
        );
    }

    #[Test]
    #[WithConfig('scout.queue', ['connection' => 'database', 'queue' => 'search'])]
    public function it_queues_the_job_on_the_connection_and_queue_of_scout_once_the_transaction_commits(): void
    {
        Queue::fake();
        $folder = $this->folder($this->acme);
        Queue::fake();

        DB::transaction(function () use ($folder): void {
            $folder->update(['client_id' => $this->globex->getKey()]);

            Queue::assertNotPushed(ReindexWatchedRows::class);
        });

        Queue::assertPushedOn('search', ReindexWatchedRows::class, fn (ReindexWatchedRows $job): bool => $job->connection === 'database');
    }

    #[Test]
    public function it_runs_the_job_synchronously_without_a_queue(): void
    {
        $folder = $this->folder($this->acme);
        Queue::fake();

        $folder->update(['client_id' => $this->globex->getKey()]);

        Queue::assertPushed(ReindexWatchedRows::class, fn (ReindexWatchedRows $job): bool => $job->connection === 'sync' && $job->queue === null);
    }

    #[Test]
    public function it_rebuilds_its_declaration_in_a_fresh_process(): void
    {
        $folder = $this->folder($this->acme);
        $article = $this->article($folder);
        Queue::fake();
        $folder->update(['client_id' => $this->globex->getKey()]);
        $job = unserialize(serialize(Queue::pushed(ReindexWatchedRows::class)->first()));
        $this->assertInstanceOf(ReindexWatchedRows::class, $job);
        $this->assertSame(["client.{$this->acme->getKey()}"], $this->accessOf($article));

        $this->bindFolderKeys();
        $this->app->call([$job, 'handle']);

        $this->assertSame(ArticleControl::class, $job->control);
        $this->assertSame(["client.{$this->globex->getKey()}"], $this->accessOf($article));
    }

    #[Test]
    public function it_throws_when_the_control_no_longer_declares_the_reindex(): void
    {
        $folder = $this->folder($this->acme);
        Queue::fake();
        $folder->update(['client_id' => $this->globex->getKey()]);
        $job = Queue::pushed(ReindexWatchedRows::class)->first();
        ConfiguredArticleControl::bind(
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys('client', fn (Article $article): mixed => $article->getAttribute('folder_id'), fn (User $user): mixed => $user->client_id),
        );

        $this->expectException(MissingReindexDeclarationException::class);
        $this->expectExceptionMessage(sprintf('A reindex job was queued for the [client] search keys of the [%s] control when [%s] changed', ArticleControl::class, Folder::class));

        $this->app->call([$job, 'handle']);
    }

    #[Test]
    public function it_throws_when_the_rows_callback_returns_no_query(): void
    {
        ConfiguredArticleControl::bind(
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys('client', fn (Article $article): mixed => $article->getAttribute('folder_id'), fn (User $user): mixed => $user->client_id)
                ->reindexWhen(Folder::class, changed: ['client_id'], rows: fn (Folder $folder) => $folder->articles()->get()),
        );

        $this->expectException(InvalidReindexRowsException::class);
        $this->expectExceptionMessage(sprintf('The rows callback of the [client] search keys returned [Illuminate\Database\Eloquent\Collection] for a [%s].', Folder::class));

        $this->folder($this->acme);
    }

    #[Test]
    public function it_throws_when_the_rows_callback_returns_rows_of_another_model(): void
    {
        ConfiguredArticleControl::bind(
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys('client', fn (Article $article): mixed => $article->getAttribute('folder_id'), fn (User $user): mixed => $user->client_id)
                ->reindexWhen(Folder::class, changed: ['client_id'], rows: fn (Folder $folder) => Post::query()),
        );

        $this->expectException(InvalidReindexRowsException::class);
        $this->expectExceptionMessage(sprintf('The rows callback of the [client] search keys returned a query of [%s] instead of [%s].', Post::class, Article::class));

        $this->folder($this->acme);
    }

    #[Test]
    public function it_takes_the_document_away_from_the_reader_of_the_former_client(): void
    {
        $folder = $this->folder($this->acme);
        $this->article($folder);
        $this->assertSame(['Scout primer'], $this->titlesFoundBy($this->ada));

        $folder->update(['client_id' => $this->globex->getKey()]);

        $this->assertSame([], $this->titlesFoundBy($this->ada));
        $this->assertSame(['Scout primer'], $this->titlesFoundBy($this->grace));
        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada, $this->grace]);
    }

    /** @return list<mixed> */
    protected function titlesFoundBy(User $reader): array
    {
        return Article::search('Scout')
            ->controlledFor($reader)
            ->get()
            ->pluck('title')
            ->all();
    }

    #[Test]
    #[WithConfig('database.connections.ledger', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''])]
    public function it_reindexes_once_the_connection_of_the_watched_model_commits_whatever_another_connection_does(): void
    {
        $folder = $this->folder($this->acme);
        $article = $this->article($folder);
        $ledger = DB::connection('ledger');

        $ledger->beginTransaction();
        $folder->update(['client_id' => $this->globex->getKey()]);
        $ledger->rollBack();

        $this->assertSame(["client.{$this->globex->getKey()}"], $this->accessOf($article));
    }

    #[Test]
    public function it_reindexes_a_watched_row_whose_attributes_are_not_utf8(): void
    {
        $folder = Folder::query()->create(['client_id' => $this->acme->getKey(), 'name' => "Archive \xff\xfe"]);
        $article = $this->article($folder);

        $folder->update(['client_id' => $this->globex->getKey()]);

        $this->assertSame(["client.{$this->globex->getKey()}"], $this->accessOf($article));
    }

    #[Test]
    public function it_queues_nothing_while_the_controlled_model_does_not_sync_to_the_search(): void
    {
        $folder = $this->folder($this->acme);
        $article = $this->article($folder);

        Article::withoutSyncingToSearch(function () use ($folder): void {
            $folder->update(['client_id' => $this->globex->getKey()]);
        });

        $this->assertSame(["client.{$this->acme->getKey()}"], $this->accessOf($article));
    }

    #[Test]
    #[WithConfig('scout.chunk.searchable', '2')]
    public function it_reads_the_chunk_size_the_way_scout_does(): void
    {
        $folder = $this->folder($this->acme);
        collect(range(1, 3))->each(fn (int $number): Article => $this->article($folder, "Scout part {$number}"));
        $this->engine->updates = [];

        $folder->update(['client_id' => $this->globex->getKey()]);

        $this->assertSame([2, 1], array_map(count(...), $this->engine->updates));
    }

    #[Test]
    public function it_refuses_overlapping_declarations_for_a_model_and_its_subclass(): void
    {
        $this->expectException(DuplicateReindexException::class);
        $this->expectExceptionMessage(sprintf('declares reindexWhen() for [%s] and for [%s], one of which extends the other', Folder::class, ArchiveFolder::class));

        ClientPerimeter::new()
            ->searchKeys('client', fn (Article $article): mixed => $article->getAttribute('folder_id'), fn (User $user): mixed => $user->client_id)
            ->reindexWhen(Folder::class, changed: ['client_id'], rows: fn (Folder $folder) => $folder->articles())
            ->reindexWhen(ArchiveFolder::class, changed: ['name'], rows: fn (ArchiveFolder $folder) => $folder->articles());
    }
}

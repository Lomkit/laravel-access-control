<?php

namespace Lomkit\Access\Tests\Feature\Search\Freshness;

use Lomkit\Access\Exceptions\MissingControlledSearchableException;
use Lomkit\Access\Search\Freshness\ReindexDocument;
use Lomkit\Access\Search\Freshness\SearchableRows;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Search\ArchivedNotice;
use Lomkit\Access\Tests\Fixtures\Search\QuietArticle;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class SearchableRowsTest extends SearchTestCase
{
    protected ArchivedNotice $kept;

    protected ArchivedNotice $trashed;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->kept = ArchivedNotice::query()->create(['title' => 'release']);
        $this->trashed = ArchivedNotice::query()->create(['title' => 'roadmap']);
        ArchivedNotice::withoutSyncingToSearch(fn (): ?bool => $this->trashed->delete());
        $this->engine->updates = [];
    }

    /** @return list<string> */
    protected function reindexed(): array
    {
        $keys = array_merge(...$this->engine->updates);
        sort($keys);

        return $keys;
    }

    protected function reindexAll(): void
    {
        SearchableRows::reindex(ArchivedNotice::class, [ArchivedNotice::query()->withTrashed()]);
    }

    #[Test]
    public function it_leaves_out_the_soft_deleted_documents_that_scout_removed_from_the_index(): void
    {
        $this->reindexAll();

        $this->assertSame([(string) $this->kept->getKey()], $this->reindexed());
    }

    #[Test]
    #[WithConfig('scout.soft_delete', true)]
    public function it_reindexes_the_soft_deleted_documents_that_scout_keeps_in_the_index(): void
    {
        $this->reindexAll();

        $this->assertSame([(string) $this->kept->getKey(), (string) $this->trashed->getKey()], $this->reindexed());
    }

    #[Test]
    public function it_ignores_the_global_scopes_of_the_rows_query(): void
    {
        SearchableRows::reindex(ArchivedNotice::class, [ArchivedNotice::query()->whereKey([$this->kept->getKey(), $this->trashed->getKey()])]);

        $this->assertSame([(string) $this->kept->getKey()], $this->reindexed());
    }

    #[Test]
    public function it_reindexes_the_union_of_several_queries_once(): void
    {
        SearchableRows::reindex(ArchivedNotice::class, [
            ArchivedNotice::query()->whereKey($this->kept->getKey()),
            ArchivedNotice::query()->where('title', 'release'),
        ]);

        $this->assertSame([[(string) $this->kept->getKey()]], $this->engine->updates);
    }

    #[Test]
    public function it_does_nothing_without_a_query(): void
    {
        SearchableRows::reindex(ArchivedNotice::class, []);

        $this->assertSame([], $this->engine->updates);
    }

    #[Test]
    public function it_refuses_a_model_that_is_not_searchable(): void
    {
        $this->expectException(MissingControlledSearchableException::class);
        $this->expectExceptionMessage(sprintf('The [%s] model does not use the ControlledSearchable trait', Post::class));

        SearchableRows::reindex(Post::class, [Post::query()]);
    }

    #[Test]
    public function it_reindexes_one_document_by_its_key(): void
    {
        (new ReindexDocument(ArchivedNotice::class, (string) $this->kept->getKey()))->handle();

        $this->assertSame([[(string) $this->kept->getKey()]], $this->engine->updates);
    }

    #[Test]
    public function it_removes_from_the_index_the_rows_that_should_no_longer_be_searchable(): void
    {
        $article = QuietArticle::query()->create(['author_id' => null, 'title' => 'Scout primer', 'body' => 'Index the rows.', 'featured' => false]);
        $article->searchable();
        QuietArticle::withoutSyncingToSearch(fn (): bool => $article->update(['title' => 'Hidden']));

        SearchableRows::reindex(QuietArticle::class, [QuietArticle::query()->whereKey($article->getKey())]);

        $this->assertArrayNotHasKey((string) $article->getKey(), $this->engine->documents[$article->searchableAs()] ?? []);
    }
}

<?php

namespace Lomkit\Access\Tests\Feature\Search\Freshness;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Lomkit\Access\Exceptions\MissingControlledSearchableException;
use Lomkit\Access\Exceptions\UnresolvablePivotException;
use Lomkit\Access\Search\Freshness\ReindexDocument;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\ArticleReviewer;
use Lomkit\Access\Tests\Fixtures\Search\FreshnessTestCase;
use Lomkit\Access\Tests\Fixtures\Search\Notice;
use Lomkit\Access\Tests\Fixtures\Search\SharedPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\UnsearchableShare;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class ReindexesSearchTest extends FreshnessTestCase
{
    protected Article $article;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        ConfiguredArticleControl::bind(
            SharedPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(function (Builder $query, User $user): void {
                    $query->whereRelation('reviewers', 'users.id', $user->getKey());
                })
                ->searchKeys(
                    name: 'reviewer',
                    row: fn (Article $article): array => $article->reviewers->modelKeys(),
                    reader: fn (User $user): mixed => $user->getKey(),
                    load: ['reviewers:id'],
                ),
        );
        $this->article = Article::factory()->createOne(['title' => 'Scout primer']);
    }

    /** @return list<string> */
    protected function updatesOf(Article|Notice $model): array
    {
        return array_values(array_filter(
            $this->engine->updates,
            fn (array $keys): bool => in_array((string) $model->getKey(), $keys, true),
        ))[0] ?? [];
    }

    protected function countUpdatesOf(Article $article): int
    {
        return count(array_filter(
            $this->engine->updates,
            fn (array $keys): bool => in_array((string) $article->getKey(), $keys, true),
        ));
    }

    #[Test]
    public function it_reindexes_the_document_when_a_pivot_is_attached_and_detached(): void
    {
        $this->article->reviewers()->attach($this->ada);
        $this->assertSame(["reviewer.{$this->ada->getKey()}"], $this->accessOf($this->article));

        $this->article->reviewers()->detach($this->ada);
        $this->assertSame([], $this->accessOf($this->article));
    }

    #[Test]
    public function it_reindexes_the_document_when_the_pivots_are_synced(): void
    {
        $this->article->reviewers()->attach($this->ada);

        $this->article->reviewers()->sync([$this->grace->getKey()]);

        $this->assertSame(["reviewer.{$this->grace->getKey()}"], $this->accessOf($this->article));
    }

    #[Test]
    public function it_reindexes_the_document_when_a_pivot_is_updated(): void
    {
        $this->article->reviewers()->attach($this->ada, ['role' => 'editor']);
        $this->engine->updates = [];

        $this->article->reviewers()->updateExistingPivot($this->ada->getKey(), ['role' => 'proofreader']);

        $this->assertSame(1, $this->countUpdatesOf($this->article));
    }

    #[Test]
    public function it_reindexes_the_document_when_a_pivot_is_toggled(): void
    {
        $this->article->reviewers()->toggle([$this->ada->getKey()]);
        $this->assertSame(["reviewer.{$this->ada->getKey()}"], $this->accessOf($this->article));

        $this->article->reviewers()->toggle([$this->ada->getKey()]);
        $this->assertSame([], $this->accessOf($this->article));
    }

    #[Test]
    #[WithConfig('scout.queue', true)]
    public function it_queues_one_job_per_document_for_many_attaches(): void
    {
        Queue::fake();
        $article = Article::factory()->createOne(['title' => 'Scout recipes']);
        $reviewers = collect(range(1, 50))->map(fn (int $number): User => $this->user("Reviewer{$number}", $this->acme));

        $article->reviewers()->attach($reviewers->map(fn (User $reviewer): mixed => $reviewer->getKey())->all());

        Queue::assertPushed(ReindexDocument::class, 1);
        Queue::assertPushed(ReindexDocument::class, fn (ReindexDocument $job): bool => $job->model === Article::class && (string) $job->key === (string) $article->getKey());
    }

    #[Test]
    public function it_reindexes_a_document_once_for_many_attaches_in_a_transaction(): void
    {
        $reviewers = collect(range(1, 50))->map(fn (int $number): User => $this->user("Reviewer{$number}", $this->acme));
        $this->engine->updates = [];

        DB::transaction(function () use ($reviewers): void {
            foreach ($reviewers as $reviewer) {
                $this->article->reviewers()->attach($reviewer);
            }

            $this->assertSame(0, $this->countUpdatesOf($this->article));
        });

        $this->assertSame(1, $this->countUpdatesOf($this->article));
        $this->assertCount(50, $this->accessOf($this->article));
    }

    #[Test]
    public function it_reindexes_nothing_when_the_transaction_rolls_back(): void
    {
        $this->engine->updates = [];

        DB::beginTransaction();
        $this->article->reviewers()->attach($this->ada);
        DB::rollBack();

        $this->assertSame(0, $this->countUpdatesOf($this->article));
        $this->assertSame([], $this->accessOf($this->article));
    }

    #[Test]
    public function it_reindexes_both_documents_when_both_sides_are_searchable(): void
    {
        $notice = Notice::query()->create(['title' => 'release']);
        $this->engine->updates = [];

        $this->article->notices()->attach($notice);

        $this->assertSame([(string) $this->article->getKey()], $this->updatesOf($this->article));
        $this->assertSame([(string) $notice->getKey()], $this->updatesOf($notice));
    }

    #[Test]
    public function it_reindexes_both_documents_from_the_other_side_of_the_relation(): void
    {
        $notice = Notice::query()->create(['title' => 'release']);
        $notice->articles()->attach($this->article);
        $this->engine->updates = [];

        $notice->articles()->detach($this->article);

        $this->assertSame([(string) $this->article->getKey()], $this->updatesOf($this->article));
        $this->assertSame([(string) $notice->getKey()], $this->updatesOf($notice));
    }

    #[Test]
    public function it_refuses_a_pivot_written_outside_its_relation_before_writing_it(): void
    {
        try {
            ArticleReviewer::query()->create(['article_id' => $this->article->getKey(), 'user_id' => $this->ada->getKey()]);
            $this->fail('The pivot was written outside its relation.');
        } catch (UnresolvablePivotException $exception) {
            $this->assertStringContainsString(sprintf('The [%s] pivot uses ReindexesSearch but was written outside its relation', ArticleReviewer::class), $exception->getMessage());
        }

        $this->assertDatabaseMissing('article_reviewers', ['article_id' => $this->article->getKey()]);
    }

    #[Test]
    public function it_refuses_to_delete_a_pivot_loaded_outside_its_relation_and_keeps_it(): void
    {
        $this->article->reviewers()->attach($this->ada);
        $pivot = ArticleReviewer::query()->where('article_id', $this->article->getKey())->firstOrFail();

        try {
            $pivot->delete();
            $this->fail('The pivot was deleted outside its relation.');
        } catch (UnresolvablePivotException) {
            $this->assertDatabaseHas('article_reviewers', ['article_id' => $this->article->getKey(), 'user_id' => $this->ada->getKey()]);
        }
    }

    #[Test]
    #[WithConfig('scout.queue', true)]
    public function it_takes_no_unique_lock_while_the_transaction_is_open(): void
    {
        Queue::fake();

        DB::transaction(function (): void {
            $this->article->reviewers()->attach($this->ada);

            $lock = Cache::lock('laravel_unique_job:'.ReindexDocument::class.':'.Article::class.':'.$this->article->getKey(), 10);
            $this->assertTrue($lock->get());
            $lock->release();
        });

        Queue::assertPushed(ReindexDocument::class, 1);
    }

    #[Test]
    public function it_leaves_the_keys_of_a_plain_pivot_to_the_application(): void
    {
        ConfiguredArticleControl::bind(
            SharedPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys(
                    name: 'shared',
                    row: fn (Article $article): array => $article->sharedWith->modelKeys(),
                    reader: fn (User $user): mixed => $user->getKey(),
                    load: ['sharedWith:id'],
                ),
        );
        $this->article->searchable();

        $this->article->sharedWith()->attach($this->ada);
        $this->assertSame([], $this->accessOf($this->article));

        $this->article->searchable();
        $this->assertSame(["shared.{$this->ada->getKey()}"], $this->accessOf($this->article));
    }

    #[Test]
    public function it_refuses_a_pivot_that_links_no_searchable_model_before_writing_it(): void
    {
        $post = Post::factory()->createOne(['title' => 'Release notes']);

        try {
            $post->belongsToMany(User::class, 'post_shares')->using(UnsearchableShare::class)->attach($this->ada);
            $this->fail('The pivot linked no searchable model.');
        } catch (MissingControlledSearchableException $exception) {
            $this->assertStringContainsString(sprintf('The [%s] pivot uses ReindexesSearch, but neither [%s] nor [%s] uses the ControlledSearchable trait', UnsearchableShare::class, Post::class, User::class), $exception->getMessage());
        }

        $this->assertDatabaseMissing('post_shares', ['post_id' => $post->getKey()]);
    }

    #[Test]
    public function it_reindexes_the_document_when_an_eager_loaded_pivot_is_deleted(): void
    {
        $this->article->reviewers()->attach($this->ada);

        $reviewer = Article::query()
            ->with('reviewers')
            ->findOrFail($this->article->getKey())
            ->reviewers
            ->firstOrFail();

        $reviewer->getRelation('pivot')->delete();

        $this->assertSame([], $this->accessOf($this->article));
    }
}

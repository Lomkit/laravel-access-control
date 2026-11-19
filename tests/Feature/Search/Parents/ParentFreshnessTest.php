<?php

namespace Lomkit\Access\Tests\Feature\Search\Parents;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Lomkit\Access\Access;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Search\Freshness\ReindexWatchedRows;
use Lomkit\Access\Search\SearchKeyDigest;
use Lomkit\Access\Tests\Fixtures\Threads\ConfiguredThreadControl;
use Lomkit\Access\Tests\Fixtures\Threads\Controls\ReplyControl;
use Lomkit\Access\Tests\Fixtures\Threads\Controls\ThreadControl;
use Lomkit\Access\Tests\Fixtures\Threads\Forum;
use Lomkit\Access\Tests\Fixtures\Threads\Reply;
use Lomkit\Access\Tests\Fixtures\Threads\Thread;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class ParentFreshnessTest extends ParentSearchTestCase
{
    /** @return list<string> */
    protected function shipItKeysFor(mixed $author, mixed $client, int $workspace = 1): array
    {
        $keys = [
            "author.{$this->grace->getKey()}",
            "through.thread.2.workspace_id.{$workspace}",
            "through.thread.2.workspace_id.{$workspace}.author.{$author}",
            "through.thread.2.workspace_id.{$workspace}.client.{$client}",
        ];

        return $this->digestsOf($keys);
    }

    /** @return list<string> */
    protected function updatedReplies(int $from): array
    {
        $bodies = [];

        foreach (array_slice($this->engine->indexUpdates, $from) as [$index, $keys]) {
            if ($index === (new Reply)->searchableAs()) {
                array_push($bodies, ...Reply::query()->whereKey($keys)->pluck('body')->all());
            }
        }

        sort($bodies);

        return $bodies;
    }

    #[Test]
    public function it_watches_the_parent_and_the_models_the_parent_watches(): void
    {
        $this->assertSame([Forum::class], Access::control(ThreadControl::class)->searchRules()->freshness()->watchedModels());
        $this->assertSame([Thread::class, Forum::class], Access::control(ReplyControl::class)->searchRules()->freshness()->watchedModels());
        $this->assertSame([ReplyControl::class], Access::getFacadeRoot()->controlsWatching(new Thread));
    }

    #[Test]
    public function it_reindexes_the_children_when_the_keys_of_the_parent_change(): void
    {
        $this->roadmap->update(['author_id' => $this->linus->getKey()]);

        $this->assertSame($this->shipItKeysFor($this->linus->getKey(), $this->acme->getKey()), $this->accessKeysOf('Ship it'));
        $this->grant(['view own threads' => ['Ada', 'Linus']]);
        $this->assertSame([], $this->searchedReplies($this->ada));
        $this->assertSame(['Ship it', 'Too expensive', 'Typo in the title'], $this->searchedReplies($this->linus));
    }

    #[Test]
    public function it_reindexes_the_children_when_the_boundary_of_the_parent_changes(): void
    {
        $this->roadmap->update(['workspace_id' => 2]);

        $this->assertSame($this->shipItKeysFor($this->ada->getKey(), $this->acme->getKey(), 2), $this->accessKeysOf('Ship it'));
    }

    #[Test]
    public function it_reindexes_no_child_when_the_parent_changes_without_changing_its_keys(): void
    {
        $updates = count($this->engine->indexUpdates);
        Queue::fake();

        $this->roadmap->update(['title' => 'Plan']);

        Queue::assertNotPushed(ReindexWatchedRows::class);
        $this->assertSame([], $this->updatedReplies($updates));
    }

    #[Test]
    public function it_reindexes_no_child_when_a_parent_is_created(): void
    {
        Queue::fake();

        $this->thread($this->general, $this->ada, 'Fresh', 1);

        Queue::assertNotPushed(ReindexWatchedRows::class, fn (ReindexWatchedRows $job): bool => $job->control === ReplyControl::class);
    }

    #[Test]
    public function it_reindexes_only_the_children_of_the_parent_that_changed(): void
    {
        $updates = count($this->engine->indexUpdates);

        $this->roadmap->update(['author_id' => $this->grace->getKey()]);

        $this->assertSame(['Ship it', 'Typo in the title'], $this->updatedReplies($updates));
    }

    #[Test]
    public function it_follows_the_parent_when_a_grandparent_reindexes_it(): void
    {
        $this->general->update(['client_id' => $this->globex->getKey()]);

        $this->assertSame($this->shipItKeysFor($this->ada->getKey(), $this->globex->getKey()), $this->accessKeysOf('Ship it'));
        $this->grant(['view client threads' => ['Linus']]);
        $this->assertSame(['Ship it', 'Too expensive', 'Typo in the title'], $this->searchedReplies($this->linus));
        $this->assertSearchAgrees(ReplyControl::class, [$this->ada, $this->linus]);
    }

    #[Test]
    public function it_follows_nested_parents_when_the_grandparent_changes_its_keys(): void
    {
        ConfiguredThreadControl::bindWithin(Boundary::none(), ThroughParent::belongsTo('forum', inverse: 'threads'));
        $this->grant(['view client forums' => ['Linus', 'Ada']]);
        $this->reindexReplies();
        $this->assertSame(['Nowhere', 'Ship it', 'Typo in the title', 'Wrong workspace'], $this->searchedReplies($this->ada));

        $this->general->update(['client_id' => $this->globex->getKey()]);

        $this->assertSame([], $this->searchedReplies($this->ada));
        $this->assertSame(['Nowhere', 'Ship it', 'Too expensive', 'Typo in the title', 'Wrong workspace'], $this->searchedReplies($this->linus));
        $this->assertSearchAgrees(ReplyControl::class, [$this->ada, $this->linus]);
    }

    #[Test]
    public function it_takes_the_keys_of_the_parent_away_when_it_is_soft_deleted_and_back_when_it_is_restored(): void
    {
        $this->grant(['view own threads' => ['Ada']]);

        $this->roadmap->delete();

        $this->assertSame($this->digestsOf(["author.{$this->grace->getKey()}"]), $this->accessKeysOf('Ship it'));
        $this->assertSame([], $this->searchedReplies($this->ada));
        $this->assertSearchAgrees(ReplyControl::class, [$this->ada]);

        $this->roadmap->restore();

        $this->assertSame($this->shipItKeysFor($this->ada->getKey(), $this->acme->getKey()), $this->accessKeysOf('Ship it'));
        $this->assertSame(['Ship it', 'Typo in the title'], $this->searchedReplies($this->ada));
    }

    #[Test]
    public function it_follows_a_child_that_moves_to_another_parent(): void
    {
        $this->replyNamed('Ship it')->update(['thread_id' => $this->pricing->getKey()]);

        $this->assertContains(SearchKeyDigest::of("through.thread.2.workspace_id.1.author.{$this->linus->getKey()}"), $this->accessKeysOf('Ship it'));
        $this->assertNotContains(SearchKeyDigest::of("through.thread.2.workspace_id.1.author.{$this->ada->getKey()}"), $this->accessKeysOf('Ship it'));
    }

    #[Test]
    public function it_reindexes_the_children_after_the_transaction_commits(): void
    {
        DB::transaction(function (): void {
            $this->roadmap->update(['author_id' => $this->linus->getKey()]);

            $this->assertSame($this->shipItKeysFor($this->ada->getKey(), $this->acme->getKey()), $this->accessKeysOf('Ship it'));
        });

        $this->assertSame($this->shipItKeysFor($this->linus->getKey(), $this->acme->getKey()), $this->accessKeysOf('Ship it'));
    }

    #[Test]
    public function it_reindexes_no_child_when_the_transaction_rolls_back(): void
    {
        DB::beginTransaction();
        $this->roadmap->update(['author_id' => $this->linus->getKey()]);
        DB::rollBack();

        $this->assertSame($this->shipItKeysFor($this->ada->getKey(), $this->acme->getKey()), $this->accessKeysOf('Ship it'));
    }

    #[Test]
    public function it_queues_one_unique_job_per_change_of_the_parent_that_rebuilds_in_a_fresh_process(): void
    {
        Queue::fake();
        $this->roadmap->update(['author_id' => $this->linus->getKey()]);
        $jobs = Queue::pushed(ReindexWatchedRows::class, fn (ReindexWatchedRows $job): bool => $job->control === ReplyControl::class);
        $this->assertCount(1, $jobs);
        $job = unserialize(serialize($jobs->first()));
        $this->assertInstanceOf(ReindexWatchedRows::class, $job);
        $this->assertSame('through.thread', $job->keys);

        $job->handle(Access::getFacadeRoot());

        $this->assertSame($this->shipItKeysFor($this->linus->getKey(), $this->acme->getKey()), $this->accessKeysOf('Ship it'));
    }
}

<?php

namespace Lomkit\Access\Tests\Feature\Search\Parents;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Search\AuthorPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Threads\ConfiguredReplyControl;
use Lomkit\Access\Tests\Fixtures\Threads\ConfiguredThreadControl;
use Lomkit\Access\Tests\Fixtures\Threads\Controls\ReplyControl;
use Lomkit\Access\Tests\Fixtures\Threads\Reply;
use Lomkit\Access\Tests\Fixtures\Threads\Thread;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class ParentSearchKeysTest extends ParentSearchTestCase
{
    /** @return list<string> */
    protected function readerKeys(User $reader): array
    {
        $keys = Access::controlFor(Reply::class)->searchFilter($reader)->accessKeys() ?? [];
        sort($keys);

        return $keys;
    }

    protected function assertEveryReaderAgrees(): void
    {
        $this->assertSearchAgrees(ReplyControl::class, [$this->ada, $this->grace, $this->linus, $this->root]);
    }

    #[Test]
    public function it_writes_a_key_per_pair_of_boundary_and_access_keys_of_the_parent(): void
    {
        $this->assertRowKeys([
            "author.{$this->grace->getKey()}",
            'through.thread.2.workspace_id.1',
            "through.thread.2.workspace_id.1.author.{$this->ada->getKey()}",
            "through.thread.2.workspace_id.1.client.{$this->acme->getKey()}",
        ], 'Ship it');
    }

    #[Test]
    public function it_writes_no_key_through_a_parent_outside_every_boundary(): void
    {
        $this->assertRowKeys(["author.{$this->grace->getKey()}"], 'Nowhere');
    }

    #[Test]
    public function it_finds_the_replies_of_the_threads_the_reader_may_view(): void
    {
        $this->grant(['view own threads' => ['Ada']]);

        $this->assertSame(["through.thread.2.workspace_id.1.author.{$this->ada->getKey()}"], $this->readerKeys($this->ada));
        $this->assertSame(['Ship it', 'Typo in the title'], $this->searchedReplies($this->ada));
        $this->assertSame([], $this->searchedReplies($this->grace));
        $this->assertEveryReaderAgrees();
    }

    #[Test]
    public function it_adds_up_the_keys_of_the_parent_and_of_the_child(): void
    {
        $this->grant(['view client threads' => ['Linus'], 'view own replies' => ['Linus']]);

        $this->assertSame([
            "author.{$this->linus->getKey()}",
            "through.thread.2.workspace_id.1.client.{$this->globex->getKey()}",
        ], $this->readerKeys($this->linus));
        $this->assertSame(['Too expensive', 'Typo in the title'], $this->searchedReplies($this->linus));
        $this->assertEveryReaderAgrees();
    }

    #[Test]
    public function it_finds_every_reply_inside_the_boundary_for_a_reader_the_parent_does_not_restrict(): void
    {
        $this->grant(['view global threads' => ['Root']]);

        $this->assertSame(['through.thread.2.workspace_id.2'], $this->readerKeys($this->root));
        $this->assertSame(['Wrong workspace'], $this->searchedReplies($this->root));
        $this->assertEveryReaderAgrees();
    }

    #[Test]
    public function it_marks_the_keys_of_a_parent_without_a_boundary(): void
    {
        ConfiguredThreadControl::bindWithin(
            Boundary::none(),
            GlobalPerimeter::new()
                ->allowed(fn (User $user): bool => $user->is($this->root))
                ->unrestricted(),
            AuthorPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(fn (Builder $query, User $user): Builder => $query->where('author_id', $user->getKey()))
                ->searchKeys(name: 'author', row: fn (Thread $thread): mixed => $thread->getAttribute('author_id'), reader: fn (User $user): mixed => $user->getKey()),
        );
        $this->reindexReplies();

        $this->assertRowKeys([
            "author.{$this->grace->getKey()}",
            'through.thread.0',
            "through.thread.0.author.{$this->ada->getKey()}",
        ], 'Ship it');
        $this->assertSame(['through.thread.0'], $this->readerKeys($this->root));
        $this->assertSame(["through.thread.0.author.{$this->ada->getKey()}"], $this->readerKeys($this->ada));
        $this->assertSame(['Nowhere', 'Ship it', 'Typo in the title'], $this->searchedReplies($this->ada));
        $this->assertCount(5, $this->searchedReplies($this->root));
        $this->assertEveryReaderAgrees();
    }

    #[Test]
    public function it_composes_the_keys_of_nested_parents(): void
    {
        ConfiguredThreadControl::bindWithin(Boundary::none(), ThroughParent::belongsTo('forum', inverse: 'threads'));
        $this->grant(['view client forums' => ['Linus'], 'view global forums' => ['Root']]);
        $this->reindexReplies();

        $this->assertSame([
            'through.thread.0',
            'through.thread.0.through.forum.0',
            "through.thread.0.through.forum.0.client.{$this->globex->getKey()}",
        ], array_values(array_filter($this->readableKeysOf('Too expensive'), fn (string $key): bool => str_starts_with($key, 'through.'))));
        $this->assertSame($this->digestsOf($this->readableKeysOf('Too expensive')), $this->accessKeysOf('Too expensive'));
        $this->assertSame(["through.thread.0.through.forum.0.client.{$this->globex->getKey()}"], $this->readerKeys($this->linus));
        $this->assertSame(['Too expensive'], $this->searchedReplies($this->linus));
        $this->assertCount(5, $this->searchedReplies($this->root));
        $this->assertEveryReaderAgrees();
    }

    #[Test]
    public function it_closes_the_search_of_a_guest_and_of_a_reader_the_parent_refuses(): void
    {
        $this->grant(['view own threads' => ['Ada']]);

        $this->assertSame([], $this->searchedReplies(null));
        $this->assertTrue(Access::controlFor(Reply::class)->searchFilter($this->grace)->isClosed());
    }

    #[Test]
    public function it_adds_no_key_when_view_is_not_delegated(): void
    {
        ConfiguredReplyControl::bind(ThroughParent::belongsTo('thread', inverse: 'replies')->delegates(['update']));
        $this->grant(['view own threads' => ['Ada'], 'update own threads' => ['Ada']]);
        $this->reindexReplies();

        $this->assertRowKeys([], 'Ship it');
        $this->assertSame([], $this->searchedReplies($this->ada));
        $this->assertEveryReaderAgrees();
    }

    #[Test]
    public function it_keeps_the_key_count_to_the_boundary_keys_times_the_access_keys_plus_one(): void
    {
        ConfiguredThreadControl::bindWithin(
            Boundary::where('workspace_id', fn (User $user): ?int => $user->current_workspace_id),
            AuthorPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => true)
                ->query(fn (Builder $query, User $user): Builder => $query->where('author_id', $user->getKey()))
                ->searchKeys(name: 'team', row: fn (Thread $thread): array => ['red', 'blue', 'green'], reader: fn (User $user): string => 'red'),
        );
        $this->reindexReplies();

        $this->assertCount(1 + 3 + 1, $this->accessKeysOf('Ship it'));
    }
}

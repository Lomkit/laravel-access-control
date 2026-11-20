<?php

namespace Lomkit\Access\Tests\Feature\Search\Parents;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Search\SearchKeyDigest;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Search\AuthorPerimeter;
use Lomkit\Access\Tests\Fixtures\Threads\ConfiguredReplyControl;
use Lomkit\Access\Tests\Fixtures\Threads\Controls\ReplyControl;
use Lomkit\Access\Tests\Fixtures\Threads\Reply;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class ParentAllowedSearchTest extends ParentSearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        ConfiguredReplyControl::bind(
            ThroughParent::belongsTo('thread', inverse: 'replies')
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} replies")),
            AuthorPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} own replies"))
                ->query(fn (Builder $query, User $user): Builder => $query->where('author_id', $user->getKey()))
                ->searchKeys(
                    name: 'author',
                    row: fn (Reply $reply): mixed => $reply->getAttribute('author_id'),
                    reader: fn (User $user): mixed => $user->getKey(),
                ),
        );
        $this->reindexReplies();
    }

    /** @return list<string> */
    protected function readableReaderKeys(User $reader): array
    {
        $keys = Access::controlFor(Reply::class)->searchFilter($reader)->readableAccessKeys() ?? [];
        sort($keys);

        return $keys;
    }

    protected function assertEveryReaderAgrees(): void
    {
        $this->assertSearchAgrees(ReplyControl::class, [$this->ada, $this->grace, $this->linus, $this->root]);
    }

    #[Test]
    public function it_gives_no_parent_key_to_a_reader_allowed_refuses(): void
    {
        $this->grant(['view own threads' => ['Ada'], 'view replies' => [], 'view own replies' => ['Ada']]);

        $this->assertSame(["author.{$this->ada->getKey()}"], $this->readableReaderKeys($this->ada));
        $this->assertSame(['Too expensive', 'Wrong workspace'], $this->searchedReplies($this->ada));
        $this->assertEveryReaderAgrees();
    }

    #[Test]
    public function it_gives_the_parent_keys_to_a_reader_allowed_accepts(): void
    {
        $this->grant(['view own threads' => ['Ada'], 'view replies' => ['Ada']]);

        $this->assertSame(["through.thread.2.workspace_id.1.author.{$this->ada->getKey()}"], $this->readableReaderKeys($this->ada));
        $this->assertSame(['Ship it', 'Typo in the title'], $this->searchedReplies($this->ada));
        $this->assertEveryReaderAgrees();
    }

    #[Test]
    public function it_keeps_the_row_keys_whatever_allowed_answers(): void
    {
        $this->assertContains(SearchKeyDigest::of("through.thread.2.workspace_id.1.author.{$this->ada->getKey()}"), $this->digestedAccessKeysOf('Ship it'));
    }
}

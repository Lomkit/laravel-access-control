<?php

namespace Lomkit\Access\Tests\Feature\Search\Parents;

use Illuminate\Support\Facades\Queue;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\UnwatchableReindexException;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Registry\ControlRegistry;
use Lomkit\Access\Search\Freshness\ReindexWatchedRows;
use Lomkit\Access\Search\Parents\ChildrenReindex;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Threads\Board;
use Lomkit\Access\Tests\Fixtures\Threads\Boards\BoardControl;
use Lomkit\Access\Tests\Fixtures\Threads\Boards\MessageControl;
use Lomkit\Access\Tests\Fixtures\Threads\ConfiguredMessageControl;
use Lomkit\Access\Tests\Fixtures\Threads\Forum;
use Lomkit\Access\Tests\Fixtures\Threads\Message;
use Lomkit\Access\Tests\Fixtures\Threads\Thread;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class InheritedReindexTest extends ParentSearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Access::discoverControlsIn([dirname(__DIR__, 3).'/Fixtures/Threads/Controls', dirname(__DIR__, 3).'/Fixtures/Threads/Boards']);

        $planning = Board::query()->create(['forum_id' => $this->general->getKey(), 'name' => 'Planning']);
        Message::query()->create(['board_id' => $planning->getKey(), 'body' => 'Kickoff']);
        $this->grant(['view client boards' => ['Ada', 'Linus']]);
    }

    protected function forgetRegistry(): void
    {
        $this->app->forgetInstance(ControlRegistry::class);
        Access::clearResolvedInstance(ControlRegistry::class);
    }

    /**
     * @param  class-string<BoardControl|MessageControl>  $control
     * @return list<class-string>
     */
    protected function watchedBy(string $control): array
    {
        return Access::control($control)
            ->searchRules()
            ->freshness()
            ->watchedModels();
    }

    /** @return list<string> */
    protected function searchedMessages(User $reader): array
    {
        return Message::search('')
            ->controlledFor($reader)
            ->get()
            ->pluck('body')
            ->all();
    }

    #[Test]
    public function it_reindexes_the_children_of_an_unsearchable_parent_when_their_grandparent_changes(): void
    {
        $this->assertSame(['Kickoff'], $this->searchedMessages($this->ada));
        $this->assertSame([], $this->searchedMessages($this->linus));

        Queue::fake();
        $this->general->update(['client_id' => $this->globex->getKey()]);
        $jobs = Queue::pushed(ReindexWatchedRows::class, fn (ReindexWatchedRows $job): bool => $job->control === MessageControl::class);
        $this->assertCount(1, $jobs);
        $this->assertSame('through.board.client', $jobs->first()->keys);
        $this->assertSame(['Kickoff'], $this->searchedMessages($this->ada));

        $job = unserialize(serialize($jobs->first()));
        $this->assertInstanceOf(ReindexWatchedRows::class, $job);
        $this->forgetRegistry();
        $job->handle(Access::getFacadeRoot());

        $this->assertSame([], $this->searchedMessages($this->ada));
        $this->assertSame(['Kickoff'], $this->searchedMessages($this->linus));
    }

    #[Test]
    public function it_accepts_the_parent_whether_it_is_resolved_before_or_after_its_searchable_child(): void
    {
        foreach ([[BoardControl::class, MessageControl::class], [MessageControl::class, BoardControl::class]] as $order) {
            $this->forgetRegistry();

            foreach ($order as $control) {
                $this->assertInstanceOf($control, Access::control($control));
            }

            $this->assertSame([Board::class, Forum::class], $this->watchedBy(MessageControl::class));
            $this->assertSame([], $this->watchedBy(BoardControl::class));
        }
    }

    #[Test]
    public function it_refuses_the_reindex_of_an_unsearchable_parent_when_no_searchable_child_finds_its_rows_through_it(): void
    {
        ConfiguredMessageControl::bind(ThroughParent::belongsTo('board', inverse: 'messages')->delegates(['update']));

        $this->expectException(UnwatchableReindexException::class);
        $this->expectExceptionMessage(sprintf('The [%s] control declares reindexWhen(), but its [%s] model does not use the ControlledSearchable trait and no searchable control inherits its keys', BoardControl::class, Board::class));

        Access::control(BoardControl::class);
    }

    #[Test]
    public function it_traces_a_derived_reindex_back_to_the_parent_that_declared_it(): void
    {
        $declared = Access::control(BoardControl::class)
            ->searchRules()
            ->freshness()
            ->declared()[0];
        $child = new ChildrenReindex($declared, 'client', Board::class, 'messages');
        $grandchild = new ChildrenReindex($child, 'through.board.client', Thread::class, 'replies');

        $this->assertTrue($child->inheritsReindexOf(Board::class));
        $this->assertTrue($grandchild->inheritsReindexOf(Board::class));
        $this->assertFalse($grandchild->inheritsReindexOf(Thread::class));
        $this->assertFalse($child->inheritsReindexOf(Forum::class));
    }
}

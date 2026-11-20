<?php

namespace Lomkit\Access\Tests\Feature\Search\Parents;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Access;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\RowContext\RequestedBrands;
use Lomkit\Access\Tests\Fixtures\Search\AuthorPerimeter;
use Lomkit\Access\Tests\Fixtures\Threads\ConfiguredReplyControl;
use Lomkit\Access\Tests\Fixtures\Threads\ConfiguredThreadControl;
use Lomkit\Access\Tests\Fixtures\Threads\Controls\ReplyControl;
use Lomkit\Access\Tests\Fixtures\Threads\Reply;
use Lomkit\Access\Tests\Fixtures\Threads\Thread;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class RowContextSearchTest extends ParentSearchTestCase
{
    private const array RIGHTS = [
        'Ada' => [1, 2],
        'Grace' => [2],
    ];

    private RequestedBrands $brands;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->brands = new RequestedBrands;
        $this->brands->request([1, 2]);
        Access::decisionContextUsing(fn (): array => $this->brands->decisionContext());
        Access::rowContextUsing(
            values: fn (): array => $this->brands->all(),
            within: fn (int|string $brand, Closure $decide): mixed => $this->brands->actingIn([(int) $brand], $decide),
        );
        Access::registerPolicies();

        Reply::withoutSyncingToSearch(function (): void {
            $this->reply($this->offsite, $this->grace, 'Offsite plan');

            foreach (['Ship it' => 1, 'Typo in the title' => 2, 'Too expensive' => 1, 'Wrong workspace' => 1, 'Nowhere' => 1, 'Offsite plan' => 2] as $body => $brand) {
                $this->replyNamed($body)->update(['workspace_id' => $brand]);
            }
        });

        ConfiguredThreadControl::bindWithin(Boundary::none(), $this->threadsOfTheRequestedBrands());
        ConfiguredReplyControl::bind($this->throughThread()->decidedInRowContext('workspace_id'));
        $this->reindexReplies();
    }

    #[Test]
    public function it_found_a_reply_of_one_brand_through_a_thread_opened_only_in_another_before(): void
    {
        ConfiguredReplyControl::bind($this->throughThread());
        $this->reindexReplies();

        $this->assertSame(['Offsite plan', 'Wrong workspace'], $this->searchedReplies($this->grace));
    }

    #[Test]
    public function it_does_not_find_a_reply_of_one_brand_through_a_thread_opened_only_in_another(): void
    {
        $this->assertSame(['Offsite plan'], $this->searchedReplies($this->grace));
        $this->assertSame(['Offsite plan', 'Ship it', 'Too expensive'], $this->searchedReplies($this->ada));
        $this->assertSearchAgrees(ReplyControl::class, [$this->ada, $this->grace, $this->linus]);
        $this->assertControlAgrees(ReplyControl::class, $this->grace, Reply::query()->orderBy('id')->get(), ['view']);
    }

    #[Test]
    public function it_writes_the_value_of_the_row_in_the_keys_through_the_parent(): void
    {
        $this->assertRowKeys(['through.thread.in.1.0', 'through.thread.in.1.0.brand.1'], 'Ship it');
        $this->assertRowKeys(['through.thread.in.2.0', 'through.thread.in.2.0.brand.1'], 'Typo in the title');
        $this->assertRowKeys(['through.thread.in.1.0'], 'Nowhere');
    }

    #[Test]
    public function it_gives_the_reader_the_keys_of_the_parent_in_each_value_of_the_request(): void
    {
        $this->assertSame(['through.thread.in.1.0.brand.1', 'through.thread.in.2.0.brand.2'], $this->readerKeys($this->ada));
        $this->assertSame(['through.thread.in.2.0.brand.2'], $this->readerKeys($this->grace));
        $this->assertSame([], $this->readerKeys($this->linus));
    }

    #[Test]
    public function it_never_shares_a_key_with_a_parent_decided_in_the_context_of_the_request(): void
    {
        ConfiguredReplyControl::bind($this->throughThread());

        $this->assertSame([], $this->searchedReplies($this->ada));
        $this->assertSame(['through.thread.0.brand.1', 'through.thread.0.brand.2'], $this->readerKeys($this->ada));
    }

    #[Test]
    public function it_reindexes_a_reply_when_its_value_changes(): void
    {
        $this->replyNamed('Wrong workspace')->update(['workspace_id' => 2]);

        $this->assertRowKeys(['through.thread.in.2.0', 'through.thread.in.2.0.brand.2'], 'Wrong workspace');
        $this->assertSame(['Offsite plan', 'Wrong workspace'], $this->searchedReplies($this->grace));
    }

    #[Test]
    public function it_reindexes_the_replies_when_the_keys_of_the_thread_change(): void
    {
        $this->offsite->update(['workspace_id' => 1]);

        $this->assertRowKeys(['through.thread.in.1.0', 'through.thread.in.1.0.brand.1'], 'Wrong workspace');
        $this->assertSame([], $this->searchedReplies($this->grace));
    }

    #[Test]
    public function it_escapes_a_value_given_as_a_string_in_the_keys(): void
    {
        $codes = ['Acme' => 1, 'globex' => 2];
        Access::rowContextUsing(
            values: fn (): array => array_keys(array_intersect($codes, $this->brands->all())),
            within: fn (int|string $code, Closure $decide): mixed => $this->brands->actingIn([$codes[$code]], $decide),
        );
        Reply::withoutSyncingToSearch(function (): void {
            foreach (['Ship it' => 'Acme', 'Typo in the title' => 'globex', 'Wrong workspace' => 'Acme', 'Offsite plan' => 'globex'] as $body => $code) {
                $this->replyNamed($body)->update(['brand_code' => $code]);
            }
        });
        ConfiguredReplyControl::bind($this->throughThread()->decidedInRowContext('brand_code'));
        $this->reindexReplies();

        $this->assertRowKeys(['through.thread.in._41636d65.0', 'through.thread.in._41636d65.0.brand.1'], 'Ship it');
        $this->assertSame(['through.thread.in._41636d65.0.brand.1', 'through.thread.in.globex.0.brand.2'], $this->readerKeys($this->ada));
        $this->assertSame(['Offsite plan'], $this->searchedReplies($this->grace));
        $this->assertSame(['Offsite plan', 'Ship it'], $this->searchedReplies($this->ada));
        $this->assertSearchAgrees(ReplyControl::class, [$this->ada, $this->grace, $this->linus]);
    }

    private function throughThread(): ThroughParent
    {
        return ThroughParent::belongsTo('thread', inverse: 'replies');
    }

    private function threadsOfTheRequestedBrands(): Perimeter
    {
        return AuthorPerimeter::new()
            ->allowed(fn (User $user): bool => $this->brandsOf($user) !== [])
            ->query(function (Builder $query, User $user): void {
                $query->whereIn('workspace_id', $this->brandsOf($user));
            })
            ->searchKeys(
                name: 'brand',
                row: fn (Thread $thread): mixed => $thread->getAttribute('workspace_id'),
                reader: fn (User $user): array => $this->brandsOf($user),
            );
    }

    /** @return list<int> */
    private function brandsOf(User $user): array
    {
        return $this->brands->within(self::RIGHTS[$user->name] ?? []);
    }

    /** @return list<string> */
    private function readerKeys(User $reader): array
    {
        $keys = Access::controlFor(Reply::class)->searchFilter($reader)->readableAccessKeys() ?? [];
        sort($keys);

        return $keys;
    }
}

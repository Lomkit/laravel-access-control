<?php

namespace Lomkit\Access\Tests\Feature\Search\Parents;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Access;
use Lomkit\Access\Boundaries\Boundary;
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
class ParentIndexKeysTest extends ParentSearchTestCase
{
    private const array RIGHTS = [
        'Ada' => [1, 2],
        'Grace' => [2],
    ];

    #[Test]
    public function it_agrees_with_the_controlled_list_through_a_parent(): void
    {
        $this->grant([
            'view own threads' => ['Ada'],
            'view client threads' => ['Linus'],
            'view global threads' => ['Root'],
            'view own replies' => ['Grace'],
        ]);

        $this->assertIndexKeysAgree(ReplyControl::class, [null, $this->ada, $this->grace, $this->linus, $this->root]);
        $this->assertSame(['Ship it', 'Typo in the title'], $this->foundBy($this->ada));
        $this->assertSame(['Nowhere', 'Ship it'], $this->foundBy($this->grace));
    }

    #[Test]
    public function it_agrees_with_the_controlled_list_through_a_parent_decided_in_the_context_of_the_row(): void
    {
        $brands = new RequestedBrands;
        $brands->request([1, 2]);
        Access::decisionContextUsing(fn (): array => $brands->decisionContext());
        Access::rowContextUsing(
            values: fn (): array => $brands->all(),
            within: fn (int|string $brand, Closure $decide): mixed => $brands->actingIn([(int) $brand], $decide),
        );
        Reply::withoutSyncingToSearch(function (): void {
            foreach (['Ship it' => 1, 'Typo in the title' => 2, 'Too expensive' => 1, 'Wrong workspace' => 2, 'Nowhere' => 1] as $body => $brand) {
                $this->replyNamed($body)->update(['workspace_id' => $brand]);
            }
        });
        ConfiguredThreadControl::bindWithin(Boundary::none(), AuthorPerimeter::new()
            ->allowed(fn (User $user): bool => $brands->within(self::RIGHTS[$user->name] ?? []) !== [])
            ->query(function (Builder $query, User $user) use ($brands): void {
                $query->whereIn('workspace_id', $brands->within(self::RIGHTS[$user->name] ?? []));
            })
            ->searchKeys(
                name: 'brand',
                row: fn (Thread $thread): mixed => $thread->getAttribute('workspace_id'),
                reader: fn (User $user): array => $brands->within(self::RIGHTS[$user->name] ?? []),
            ));
        ConfiguredReplyControl::bind(ThroughParent::belongsTo('thread', inverse: 'replies')->decidedInRowContext('workspace_id'));

        $this->assertIndexKeysAgree(ReplyControl::class, [$this->ada, $this->grace, $this->linus]);
        $this->assertSame(['Wrong workspace'], $this->foundBy($this->grace));
    }

    /** @return list<string> */
    private function foundBy(User $reader): array
    {
        $rows = Reply::query()->orderBy('id')->get();
        $bodies = $rows->pluck('body', 'id')->all();
        $values = Access::readerKeysFor(Reply::class, $reader)->values();
        $found = [];

        foreach (Access::searchKeysOf($rows) as $key => $document) {
            if (array_intersect($document['_access'], $values['_access']) !== [] && array_intersect($document['_boundary'], $values['_boundary']) !== []) {
                $found[] = $bodies[$key];
            }
        }

        sort($found);

        return $found;
    }
}

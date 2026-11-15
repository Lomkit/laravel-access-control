<?php

namespace Lomkit\Access\Tests\Feature\Search\Parents;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Exceptions\DuplicateSearchKeyNameException;
use Lomkit\Access\Exceptions\UnsearchableThroughParentException;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Blog\InMemoryWorkspaceBoundary;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Search\AuthorPerimeter;
use Lomkit\Access\Tests\Fixtures\Threads\ConfiguredForumControl;
use Lomkit\Access\Tests\Fixtures\Threads\ConfiguredReplyControl;
use Lomkit\Access\Tests\Fixtures\Threads\ConfiguredThreadControl;
use Lomkit\Access\Tests\Fixtures\Threads\Forum;
use Lomkit\Access\Tests\Fixtures\Threads\Reply;
use Lomkit\Access\Tests\Fixtures\Threads\Thread;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class ParentSearchDeclarationTest extends ParentSearchTestCase
{
    #[Test]
    public function it_requires_the_inverse_on_a_searchable_child(): void
    {
        ConfiguredReplyControl::bind(ThroughParent::belongsTo('thread'));

        $this->expectException(UnsearchableThroughParentException::class);
        $this->expectExceptionMessage(sprintf(
            'The ThroughParent perimeter of the [%s] control through the [thread] relation has no inverse, but the documents of its rows carry the access keys of the [%s] model.',
            ConfiguredReplyControl::class,
            Thread::class,
        ));

        Access::controlFor(Reply::class);
    }

    #[Test]
    public function it_requires_the_inverse_of_every_parent_whose_keys_reach_a_searchable_child(): void
    {
        ConfiguredForumControl::bind(ThroughParent::belongsTo('client'));
        ConfiguredThreadControl::bindWithin(Boundary::none(), ThroughParent::belongsTo('forum', inverse: 'threads'));

        $this->expectException(UnsearchableThroughParentException::class);
        $this->expectExceptionMessage(sprintf('The ThroughParent perimeter of the [%s] control through the [client] relation has no inverse', ConfiguredForumControl::class));

        Access::controlFor(Thread::class);
    }

    #[Test]
    public function it_needs_no_inverse_on_a_child_that_is_not_searchable(): void
    {
        ConfiguredForumControl::bind(ThroughParent::belongsTo('client'));
        $this->grant(['view global clients' => ['Ada']]);

        $this->assertSame(2, Forum::query()->tap(fn (Builder $query) => Access::controlFor(Forum::class)->restrict($query, $this->ada, Ability::view()))->count());
    }

    #[Test]
    public function it_refuses_to_search_a_child_whose_view_maps_to_another_ability_of_the_parent(): void
    {
        ConfiguredReplyControl::bind(ThroughParent::belongsTo('thread', inverse: 'replies')->delegates(['view' => 'update']));

        $this->expectException(UnsearchableThroughParentException::class);
        $this->expectExceptionMessage('delegates view to the [update] ability of the parent, but a search only knows the keys that open the parent for view.');

        Access::controlFor(Reply::class);
    }

    #[Test]
    public function it_refuses_to_search_a_child_through_a_parent_whose_boundary_has_no_search_form(): void
    {
        ConfiguredThreadControl::bindWithin(new InMemoryWorkspaceBoundary, AuthorPerimeter::new()
            ->allowed(fn (User $user): bool => true)
            ->query(fn (Builder $query, User $user): Builder => $query->where('author_id', $user->getKey())));

        $this->expectException(UnsearchableThroughParentException::class);
        $this->expectExceptionMessage(sprintf('inherits the access of the [%s] control, whose boundary has no search form', ConfiguredThreadControl::class));

        Access::controlFor(Reply::class);
    }

    #[Test]
    public function it_reserves_the_name_of_the_keys_through_a_parent(): void
    {
        ConfiguredReplyControl::bind(
            ThroughParent::belongsTo('thread', inverse: 'replies'),
            AuthorPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(fn (Builder $query, User $user): Builder => $query->where('author_id', $user->getKey()))
                ->searchKeys(name: 'through', row: fn (Reply $reply): mixed => $reply->getAttribute('author_id'), reader: fn (User $user): mixed => $user->getKey()),
        );

        $this->expectException(DuplicateSearchKeyNameException::class);
        $this->expectExceptionMessage(sprintf('A perimeter of the [%s] control declares search keys named [through], the name that prefixes the keys its ThroughParent perimeters inherit', ConfiguredReplyControl::class));

        Access::controlFor(Reply::class);
    }
}

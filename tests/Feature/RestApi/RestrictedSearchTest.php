<?php

namespace Lomkit\Access\Tests\Feature\RestApi;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Exceptions\InvalidRestReaderException;
use Lomkit\Access\Integrations\RestApi\AccessControlExtension;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredCommentControl;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\Fixtures\RestApi\CommentResource;
use Lomkit\Access\Tests\Fixtures\RestApi\PostResource;
use Lomkit\Rest\Facades\Rest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('rest-api')]
class RestrictedSearchTest extends RestApiTestCase
{
    #[Test]
    public function it_searches_the_rows_that_controlled_finds_for_the_user_through_levels_and_the_boundary(): void
    {
        $this->grant(['view client posts' => ['Ada'], 'view own posts' => ['Grace'], 'view global posts' => ['Root']]);

        $this->assertSame(['Release notes', 'Roadmap', 'Archive'], $this->searched('posts', $this->ada, 'title'));
        $this->assertSame(['Roadmap'], $this->searched('posts', $this->grace, 'title'));
        $this->assertSame(['Elsewhere'], $this->searched('posts', $this->root, 'title'));
        $this->assertSame(['Release notes', 'Roadmap', 'Archive'], $this->titlesOf($this->ada));
        $this->assertSame(['Elsewhere'], $this->titlesOf($this->root));
        $this->search('posts', $this->linus)->assertForbidden();
    }

    #[Test]
    public function it_searches_the_children_that_inherit_the_access_of_their_parent(): void
    {
        $this->grant(['view client posts' => ['Ada'], 'view own comments' => ['Ada']]);

        $this->assertSame(['Ship it', 'Typo in the title', 'Add search', 'Old news', 'Too expensive'], $this->searched('comments', $this->ada, 'body'));
        $this->assertSame($this->visibleComments($this->ada), $this->searched('comments', $this->ada, 'body'));
    }

    #[Test]
    public function it_searches_the_children_of_a_parent_that_inherits_the_access_of_its_own_parent(): void
    {
        $this->grant(['view client projects' => ['Ada', 'Linus']]);
        ConfiguredPostControl::bind(ThroughParent::belongsTo('project', inverse: 'posts'));

        $this->assertSame(['Release notes', 'Roadmap', 'Archive'], $this->searched('posts', $this->ada, 'title'));
        $this->assertSame(['Ship it', 'Typo in the title', 'Add search', 'Old news'], $this->searched('comments', $this->ada, 'body'));
        $this->assertSame(['Too expensive', 'Agreed'], $this->searched('comments', $this->linus, 'body'));
        $this->assertSame($this->visibleComments($this->linus), $this->searched('comments', $this->linus, 'body'));
    }

    #[Test]
    public function it_restricts_the_rows_of_an_include(): void
    {
        $this->grant(['view client posts' => ['Ada'], 'view own comments' => ['Ada']]);

        $response = $this->search('comments', $this->ada, ['includes' => [['relation' => 'post']]])->assertOk();

        $this->assertSame(
            ['Ship it' => 'Release notes', 'Typo in the title' => 'Release notes', 'Add search' => 'Roadmap', 'Old news' => 'Archive', 'Too expensive' => null],
            array_column(array_map(fn (array $comment): array => ['body' => $comment['body'], 'post' => $comment['post']['title'] ?? null], $response->json('data')), 'post', 'body'),
        );
    }

    #[Test]
    public function it_restricts_the_related_rows_of_a_has_many_include_an_aggregate_and_a_relation_filter(): void
    {
        $this->grant(['view client posts' => ['Ada'], 'view own comments' => ['Ada']]);
        ConfiguredCommentControl::bind($this->ownComments());

        $response = $this->search('posts', $this->ada, [
            'includes' => [['relation' => 'comments']],
            'aggregates' => [['relation' => 'comments', 'type' => 'count']],
        ])->assertOk();

        $this->assertSame([[], ['Add search'], []], array_map(fn (array $post): array => array_column($post['comments'], 'body'), $response->json('data')));
        $this->assertSame([0, 1, 0], array_column($response->json('data'), 'comments_count'));
        $this->assertSame([], $this->searched('posts', $this->ada, 'title', ['filters' => [['field' => 'comments.body', 'value' => 'Ship it']]]));
        $this->assertSame(['Roadmap'], $this->searched('posts', $this->ada, 'title', ['filters' => [['field' => 'comments.body', 'value' => 'Add search']]]));
    }

    #[Test]
    public function it_filters_through_a_parent_that_the_user_cannot_see_as_through_no_row(): void
    {
        $this->grant(['view client posts' => ['Ada'], 'view own comments' => ['Ada']]);

        $this->assertSame([], $this->searched('comments', $this->ada, 'body', ['filters' => [['field' => 'post.title', 'value' => 'Pricing']]]));
        $this->assertSame(['Add search'], $this->searched('comments', $this->ada, 'body', ['filters' => [['field' => 'post.title', 'value' => 'Roadmap'], ['field' => 'author_id', 'value' => $this->ada->getKey()]]]));
    }

    #[Test]
    public function it_counts_the_rows_of_a_metric_through_the_control(): void
    {
        $this->grant(['view client posts' => ['Ada']]);

        $this->actingAs($this->ada)
            ->postJson('/posts/metrics/post-count')
            ->assertOk()
            ->assertJsonPath('data.value', 3);
    }

    #[Test]
    public function it_shows_a_guest_the_rows_that_controlled_shows_a_guest(): void
    {
        Gate::before(fn (?User $user): bool => true);

        $this->assertSame([], Rest::search(PostResource::class)->as(null)->payload([])->page()->items());
        $this->assertSame([], Rest::search(CommentResource::class)->as(null)->payload([])->page()->items());
        $this->assertSame([], $this->visibleComments(null));
        $this->assertSame(5, Post::query()->count());
    }

    #[Test]
    public function it_refuses_a_user_that_is_not_an_eloquent_model(): void
    {
        Gate::before(fn (mixed $user): bool => true);
        $this->withoutExceptionHandling();

        $this->expectException(InvalidRestReaderException::class);
        $this->expectExceptionMessage(sprintf('The [%s] rest-api extension received a user of the [%s] class, which is not an Eloquent model', AccessControlExtension::class, GenericUser::class));

        $this->actingAs(new GenericUser(['id' => $this->ada->getKey()]))->postJson('/posts/search', ['search' => []]);
    }

    /** @return list<string> */
    private function titlesOf(User $reader): array
    {
        return array_values(Post::controlledFor($reader)->orderBy('id')->pluck('title')->all());
    }

    private function ownComments(): OwnPerimeter
    {
        return OwnPerimeter::new()
            ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} own comments"))
            ->query(fn (Builder $query, User $user): Builder => $query->where('author_id', $user->getKey()));
    }
}

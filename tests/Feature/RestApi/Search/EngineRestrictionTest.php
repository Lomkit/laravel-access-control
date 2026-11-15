<?php

namespace Lomkit\Access\Tests\Feature\RestApi\Search;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Search\SearchField;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\RestApi\Search\ReplyResource;
use Lomkit\Access\Tests\Fixtures\RestApi\Search\ThreadResource;
use Lomkit\Access\Tests\Fixtures\Threads\Controls\ReplyControl;
use Lomkit\Access\Tests\Fixtures\Threads\Controls\ThreadControl;
use Lomkit\Access\Tests\Fixtures\Threads\Reply;
use Lomkit\Access\Tests\Fixtures\Threads\Thread;
use Lomkit\Rest\Facades\Rest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('rest-api')]
#[Group('scout')]
class EngineRestrictionTest extends RestApiSearchTestCase
{
    #[Test]
    public function it_finds_on_the_index_the_documents_that_a_controlled_search_finds(): void
    {
        $this->grant(['view client threads' => ['Ada', 'Linus'], 'view own threads' => ['Grace']]);

        $this->assertSame(['Roadmap'], $this->found('threads', $this->ada, 'o', 'title'));
        $this->assertSame(['Pricing'], $this->found('threads', $this->linus, 'i', 'title'));
        $this->assertSame([], $this->found('threads', $this->grace, 'o', 'title'));
        $this->assertSame($this->controlledTitles($this->ada, 'o'), $this->found('threads', $this->ada, 'o', 'title'));
        $this->assertSame(
            [SearchField::Boundary->value => ['workspace_id.1'], SearchField::Access->value => ['client.'.$this->acme->getKey()]],
            $this->index->searches[0]->whereIns,
        );
        $this->assertSearchAgrees(ThreadControl::class, [$this->ada, $this->grace, $this->linus, $this->root]);
    }

    #[Test]
    public function it_keeps_an_unrestricted_reader_inside_the_boundary_only(): void
    {
        $this->grant(['view global threads' => ['Root', 'Ada']]);

        $this->assertSame(['Roadmap'], $this->found('threads', $this->ada, 'o', 'title'));
        $this->assertSame([SearchField::Boundary->value => ['workspace_id.1']], $this->index->searches[0]->whereIns);
        $this->assertSame(['Offsite'], $this->found('threads', $this->root, 'o', 'title'));
        $this->assertSearchAgrees(ThreadControl::class, [$this->ada, $this->root]);
    }

    #[Test]
    public function it_finds_the_children_through_the_composite_keys_of_their_parent(): void
    {
        $this->grant(['view client threads' => ['Ada'], 'view own replies' => ['Ada']]);

        $this->assertSame(['Ship it', 'Too expensive', 'Typo in the title'], $this->found('replies', $this->ada, 'i', 'body'));
        $this->assertSame($this->controlledReplies($this->ada, 'i'), $this->found('replies', $this->ada, 'i', 'body'));
        $this->assertSame(
            [SearchField::Access->value => ['through.thread.2.workspace_id.1.client.'.$this->acme->getKey(), 'author.'.$this->ada->getKey()]],
            $this->index->searches[0]->whereIns,
        );
        $this->assertSearchAgrees(ReplyControl::class, [$this->ada, $this->grace, $this->linus]);
    }

    #[Test]
    public function it_matches_nothing_without_calling_the_engine_for_a_closed_reader(): void
    {
        Gate::before(fn (?User $user): bool => true);

        $this->assertSame([], Rest::search(ThreadResource::class)->as(null)->payload(['text' => ['value' => 'o']])->page()->items());
        $this->assertSame([], Rest::search(ReplyResource::class)->as(null)->payload(['text' => ['value' => 'i']])->page()->items());
        $this->assertSame([], $this->index->searches);
    }

    #[Test]
    public function it_drops_a_row_that_the_index_still_matches_but_the_control_now_hides(): void
    {
        $this->grant(['view client threads' => ['Ada']]);
        DB::table('threads')->where('id', $this->roadmap->getKey())->update(['forum_id' => $this->support->getKey()]);

        $response = $this->textSearch('threads', $this->ada, 'o')->assertOk();

        $this->assertSame([], $response->json('data'));
        $this->assertSame(1, $response->json('total'));
        $this->assertSame([], Thread::controlledFor($this->ada)->pluck('title')->all());
    }

    #[Test]
    public function it_declares_the_access_keys_as_lists_that_clients_cannot_filter(): void
    {
        $this->grant(['view client threads' => ['Ada']]);

        $this->actingAs($this->ada)
            ->postJson('/threads/search', ['search' => ['text' => ['value' => 'o'], 'filters' => [['field' => SearchField::Access->value, 'value' => 'client.'.$this->globex->getKey()]]]])
            ->assertUnprocessable();
    }

    /** @return list<string> */
    private function controlledTitles(User $reader, string $text): array
    {
        return Thread::search($text)->controlledFor($reader)->get()->pluck('title')->sort()->values()->all();
    }

    /** @return list<string> */
    private function controlledReplies(User $reader, string $text): array
    {
        return Reply::query()
            ->whereIn('id', Reply::search('')->controlledFor($reader)->keys())
            ->where('body', 'like', "%{$text}%")
            ->orderBy('body')
            ->pluck('body')
            ->all();
    }
}

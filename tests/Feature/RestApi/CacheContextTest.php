<?php

namespace Lomkit\Access\Tests\Feature\RestApi;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredCommentControl;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('rest-api')]
class CacheContextTest extends RestApiTestCase
{
    private string $tenant = 'acme';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Access::decisionContextUsing(fn (): array => ['tenant' => $this->tenant]);
        DB::table('posts')->update(['updated_at' => '2026-01-01 00:00:00']);
        DB::table('comments')->update(['updated_at' => '2026-01-01 00:00:00']);
        DB::table('projects')->update(['updated_at' => '2026-01-01 00:00:00']);
    }

    #[Test]
    public function it_never_reads_a_decision_kept_in_another_decision_context(): void
    {
        $this->grant(['view client posts' => ['Ada'], 'update client posts' => ['Ada']]);
        $this->assertSame(['Release notes' => true, 'Roadmap' => true, 'Archive' => false], $this->gates('cached-posts', 'title', 'update'));

        $this->grant(['update client posts' => []]);

        $this->assertSame(['Release notes' => true, 'Roadmap' => true, 'Archive' => false], $this->gates('cached-posts', 'title', 'update'));
        $this->tenant = 'globex';
        $this->assertSame(['Release notes' => false, 'Roadmap' => false, 'Archive' => false], $this->gates('cached-posts', 'title', 'update'));
        $this->tenant = 'acme';
        $this->assertSame(['Release notes' => true, 'Roadmap' => true, 'Archive' => false], $this->gates('cached-posts', 'title', 'update'));
    }

    #[Test]
    public function it_keeps_a_decision_that_a_child_inherits_while_its_parent_is_unchanged(): void
    {
        $this->inheritUpdatesFromPosts();
        $this->assertSame(true, $this->gates('cached-comments', 'body', 'update')['Ship it']);

        $this->grant(['update client posts' => []]);

        $this->assertSame(true, $this->gates('cached-comments', 'body', 'update')['Ship it']);
    }

    #[Test]
    public function it_renews_a_decision_that_a_child_inherits_when_its_parent_changes(): void
    {
        $this->inheritUpdatesFromPosts();
        $this->assertSame(true, $this->gates('cached-comments', 'body', 'update')['Ship it']);

        DB::table('posts')->where('id', $this->releaseNotes->getKey())->update(['archived_at' => '2026-02-01 00:00:00', 'updated_at' => '2026-02-01 00:00:00']);

        $this->assertSame(false, $this->gates('cached-comments', 'body', 'update')['Ship it']);
    }

    #[Test]
    public function it_renews_a_decision_that_a_child_inherits_when_a_grandparent_changes(): void
    {
        $this->grant(['view global posts' => ['Ada'], 'update client projects' => ['Ada']]);
        ConfiguredPostControl::bind(
            GlobalPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} global posts"))
                ->unrestricted(),
            ThroughParent::belongsTo('project')->delegates(['update']),
        );
        ConfiguredCommentControl::bind(ThroughParent::belongsTo('post', inverse: 'comments')->delegates(['view', 'update']));
        $this->assertSame(true, $this->gates('cached-comments', 'body', 'update')['Ship it']);

        DB::table('projects')->where('name', 'Website')->update(['client_id' => Client::query()->where('name', 'Globex')->value('id'), 'updated_at' => '2026-02-01 00:00:00']);

        $this->assertSame(false, $this->gates('cached-comments', 'body', 'update')['Ship it']);
    }

    #[Test]
    public function it_keeps_no_decision_that_a_child_inherits_from_a_parent_without_updated_at(): void
    {
        DB::table('posts')->update(['updated_at' => null]);
        $this->inheritUpdatesFromPosts();
        $this->assertSame(true, $this->gates('cached-comments', 'body', 'update')['Ship it']);

        $this->grant(['update client posts' => []]);

        $this->assertSame(false, $this->gates('cached-comments', 'body', 'update')['Ship it']);
    }

    #[Test]
    public function it_still_keeps_the_decisions_that_a_child_takes_alone_when_its_parent_changes(): void
    {
        $this->grant(['view client posts' => ['Ada'], 'update own comments' => ['Ada']]);
        ConfiguredCommentControl::bind(
            ThroughParent::belongsTo('post', inverse: 'comments'),
            OwnPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} own comments"))
                ->query(fn (Builder $query, User $user): Builder => $query->where('author_id', $user->getKey())),
        );
        $this->assertSame(true, $this->gates('cached-comments', 'body', 'update')['Add search']);

        DB::table('posts')->where('id', $this->roadmap->getKey())->update(['title' => 'Plan', 'updated_at' => '2026-02-01 00:00:00']);
        $this->grant(['update own comments' => []]);

        $this->assertSame(true, $this->gates('cached-comments', 'body', 'update')['Add search']);
    }

    #[Test]
    public function it_reads_the_parent_versions_of_a_page_in_the_same_queries_whatever_its_size(): void
    {
        $this->inheritUpdatesFromPosts();
        $this->gates('cached-comments', 'body', 'update');
        $counts = [];

        foreach ([2, 8] as $added) {
            for ($index = 0; $index < $added; $index++) {
                $this->comment($this->roadmap, $this->ada, "Extra {$added}.{$index}", 1);
            }

            DB::table('comments')->update(['updated_at' => '2026-01-01 00:00:00']);
            $counts[] = count($this->queriesWhile(fn () => $this->gates('cached-comments', 'body', 'update')));
        }

        $this->assertSame($counts[0], $counts[1]);
    }

    private function inheritUpdatesFromPosts(): void
    {
        $this->grant(['view client posts' => ['Ada'], 'update client posts' => ['Ada']]);
        ConfiguredCommentControl::bind(ThroughParent::belongsTo('post', inverse: 'comments')->delegates(['view', 'update']));
    }

    /** @return array<string, bool> */
    private function gates(string $resource, string $label, string $gate): array
    {
        $rows = $this->search($resource, $this->ada, ['gates' => [$gate]])->assertOk()->json('data');

        return array_combine(array_column($rows, $label), array_map(fn (array $row): bool => $row['gates']["authorized_to_{$gate}"]['allowed'], $rows));
    }
}

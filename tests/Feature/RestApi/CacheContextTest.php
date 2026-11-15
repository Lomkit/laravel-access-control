<?php

namespace Lomkit\Access\Tests\Feature\RestApi;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredCommentControl;
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
    public function it_keeps_no_decision_that_a_child_inherits_from_its_parent(): void
    {
        $this->grant(['view client posts' => ['Ada'], 'update client posts' => ['Ada']]);
        ConfiguredCommentControl::bind(ThroughParent::belongsTo('post', inverse: 'comments')->delegates(['view', 'update']));
        $this->assertSame(true, $this->gates('cached-comments', 'body', 'update')['Ship it']);

        $this->releaseNotes->update(['archived_at' => '2026-02-01 00:00:00']);

        $this->assertSame(false, $this->gates('cached-comments', 'body', 'update')['Ship it']);
    }

    #[Test]
    public function it_still_keeps_the_decisions_that_a_child_takes_alone(): void
    {
        $this->grant(['view client posts' => ['Ada'], 'update own comments' => ['Ada']]);
        ConfiguredCommentControl::bind(
            ThroughParent::belongsTo('post', inverse: 'comments'),
            OwnPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} own comments"))
                ->query(fn (Builder $query, User $user): Builder => $query->where('author_id', $user->getKey())),
        );
        $this->assertSame(true, $this->gates('cached-comments', 'body', 'update')['Add search']);

        $this->grant(['update own comments' => []]);

        $this->assertSame(true, $this->gates('cached-comments', 'body', 'update')['Add search']);
    }

    /** @return array<string, bool> */
    private function gates(string $resource, string $label, string $gate): array
    {
        $rows = $this->search($resource, $this->ada, ['gates' => [$gate]])->assertOk()->json('data');

        return array_combine(array_column($rows, $label), array_map(fn (array $row): bool => $row['gates']["authorized_to_{$gate}"]['allowed'], $rows));
    }
}

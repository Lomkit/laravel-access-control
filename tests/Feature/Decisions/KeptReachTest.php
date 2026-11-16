<?php

namespace Lomkit\Access\Tests\Feature\Decisions;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Decisions\DecisionMemo;
use Lomkit\Access\Decisions\KeptReach;
use Lomkit\Access\Decisions\ReachKey;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Perimeters\ReachedPerimeters;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\Editor;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Blog\WorkspaceBoundary;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use PHPUnit\Framework\Attributes\Test;

class KeptReachTest extends DecisionTestCase
{
    /** @var array<string, int> */
    private array $calls = [];

    private ?int $queries = null;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        DB::listen(function (): void {
            if ($this->queries !== null) {
                $this->queries++;
            }
        });
    }

    #[Test]
    public function it_never_shares_the_perimeters_reached_between_readers(): void
    {
        ConfiguredPostControl::bind($this->ownInSql(fn (User $user): bool => $user::class === User::class && $user->is($this->ada)));
        $editor = (new Editor)->newFromBuilder($this->ada->getAttributes());

        $this->assertTrue($this->ada->can('update', $this->adaPost));
        $this->assertSame('no_perimeter', $this->inspect($this->grace, 'update', $this->adaPost)->code());
        $this->assertSame('no_perimeter', $this->inspect($editor, 'update', $this->adaPost)->code());
        $this->assertTrue($this->ada->can('update', $this->adaPost));
    }

    #[Test]
    public function it_evaluates_a_querying_allowed_once_for_every_row_after_a_preload(): void
    {
        ConfiguredPostControl::bind($this->ownInSql($this->querying('own')));
        $posts = $this->adaPosts(20);

        $queries = $this->countQueries(function () use ($posts): void {
            Access::preload($this->ada, 'update', $posts);

            foreach ($posts as $post) {
                $this->assertTrue($this->ada->can('update', $post));
            }
        });

        $this->assertSame(2, $queries);
        $this->assertSame(['own' => 1], $this->calls);
    }

    #[Test]
    public function it_evaluates_a_querying_allowed_once_for_every_row_after_with_abilities(): void
    {
        ConfiguredPostControl::bind($this->ownInSql($this->querying('own')));
        $this->adaPosts(20);

        $queries = $this->countQueries(function (): void {
            $posts = Post::controlledFor($this->ada, 'update')->withAbilities(['update'])->get();

            $this->assertCount(21, $posts);

            foreach ($posts as $post) {
                $this->assertTrue($this->ada->can('update', $post));
            }
        });

        $this->assertSame(2, $queries);
        $this->assertSame(['own' => 1], $this->calls);
    }

    #[Test]
    public function it_evaluates_a_querying_allowed_once_per_row_check_in_sql(): void
    {
        ConfiguredPostControl::bind($this->ownInSql($this->querying('own')));
        $posts = $this->adaPosts(20);

        $queries = $this->countQueries(function () use ($posts): void {
            foreach ($posts as $post) {
                $this->assertTrue($this->ada->can('update', $post));
            }
        });

        $this->assertSame(1 + 21, $queries);
        $this->assertSame(['own' => 1], $this->calls);
    }

    #[Test]
    public function it_evaluates_the_allowed_once_for_each_ability(): void
    {
        ConfiguredPostControl::bind($this->ownInSql($this->querying('own')));
        $posts = $this->adaPosts(5);
        Access::preload($this->ada, 'update', $posts);
        Access::preload($this->ada, 'delete', $posts);

        foreach ($posts as $post) {
            $this->assertTrue($this->ada->can('update', $post));
            $this->assertTrue($this->ada->can('delete', $post));
        }

        $this->assertSame(['own' => 2], $this->calls);
    }

    #[Test]
    public function it_evaluates_the_allowed_again_for_another_decision_context(): void
    {
        $brand = 1;
        Access::decisionContextUsing(function () use (&$brand): array {
            return ['brand' => $brand];
        });
        ConfiguredPostControl::bind($this->ownInSql($this->querying('own')));

        $this->assertTrue($this->ada->can('update', $this->adaPost));
        $this->assertFalse($this->ada->can('update', $this->teamPost));
        $brand = 2;
        $this->assertTrue($this->ada->can('update', $this->adaPost));
        $brand = 1;
        $this->assertTrue($this->ada->can('update', $this->adaPost));

        $this->assertSame(['own' => 2], $this->calls);
    }

    #[Test]
    public function it_evaluates_the_allowed_again_after_a_flush(): void
    {
        ConfiguredPostControl::bind($this->ownInSql($this->querying('own')));

        $this->assertTrue($this->ada->can('update', $this->adaPost));
        $this->assertTrue($this->ada->can('update', $this->adaPost));
        Access::forgetDecisions();
        $this->assertTrue($this->ada->can('update', $this->adaPost));
        Client::query()->firstOrFail()->update(['name' => 'Acme Corp']);
        $this->assertTrue($this->ada->can('update', $this->adaPost));
        DB::beginTransaction();
        $this->assertTrue($this->ada->can('update', $this->adaPost));
        DB::rollBack();
        $this->assertTrue($this->ada->can('update', $this->adaPost));

        $this->assertSame(['own' => 4], $this->calls);
    }

    #[Test]
    public function it_keeps_the_perimeters_reached_until_a_flush(): void
    {
        $allowed = true;
        ConfiguredPostControl::bind($this->ownInSql(function (User $user) use (&$allowed): bool {
            return $allowed;
        }));

        $this->assertTrue($this->ada->can('update', $this->adaPost));
        $allowed = false;
        $this->assertTrue($this->ada->can('update', $this->adaPost));
        Access::forgetDecisions();
        $this->assertSame('no_perimeter', $this->inspect($this->ada, 'update', $this->adaPost)->code());
    }

    #[Test]
    public function it_evaluates_the_allowed_of_each_level_once(): void
    {
        ConfiguredPostControl::bind(Levels::of(
            $this->clientInSql($this->querying('client', false)),
            $this->ownInSql($this->querying('own')),
        ));

        foreach ($this->adaPosts(5) as $post) {
            $this->assertTrue($this->ada->can('update', $post));
        }

        $this->assertFalse($this->ada->can('update', $this->teamPost));
        $this->assertSame(['client' => 1, 'own' => 1], $this->calls);
    }

    #[Test]
    public function it_admits_the_reader_once_for_the_boundary(): void
    {
        $boundary = new class($this->calls) extends WorkspaceBoundary
        {
            /** @param  array<string, int>  $calls */
            public function __construct(private array &$calls) {}

            #[\Override]
            protected function admits(AccessContext $context): bool
            {
                $this->calls['admits'] = ($this->calls['admits'] ?? 0) + 1;

                return true;
            }
        };
        ConfiguredPostControl::bindWithin($boundary, $this->ownInSql());
        $elsewhere = $this->adaPosts(5, workspace: 2);

        foreach ($elsewhere as $post) {
            $this->assertSame('not_found', $this->inspect($this->ada, 'update', $post)->code());
        }

        $this->assertSame(['admits' => 2], $this->calls);
    }

    #[Test]
    public function it_shares_the_perimeters_reached_between_the_list_and_the_policy(): void
    {
        ConfiguredPostControl::bind($this->ownInSql($this->querying('own')));

        foreach (Post::controlledFor($this->ada, 'update')->get() as $post) {
            $this->assertTrue($this->ada->can('update', $post));
        }

        $this->assertSame(['own' => 1], $this->calls);
    }

    #[Test]
    public function it_evaluates_the_allowed_on_every_decision_for_a_reader_that_is_not_kept(): void
    {
        ConfiguredPostControl::bind($this->ownInSql($this->querying('own')));
        $control = Access::controlFor(Post::class);
        $reader = (new User)->forceFill(['id' => $this->ada->getKey(), 'name' => 'Ada', 'current_workspace_id' => 1]);

        foreach ([$this->adaPost, $this->teamPost, $this->elsewhere] as $post) {
            $control->applies($reader, Ability::update(), $post);
        }

        $this->assertSame(['own' => 3], $this->calls);
        $this->assertSame(0, $this->memo()->reachCount());
    }

    #[Test]
    public function it_evaluates_the_perimeters_again_for_another_instance_of_the_control(): void
    {
        ConfiguredPostControl::bind($this->ownInSql($this->querying('own')));
        $this->assertTrue($this->ada->can('update', $this->adaPost));

        ConfiguredPostControl::bind($this->ownInSql($this->querying('refused', false)));

        $this->assertSame('no_perimeter', $this->inspect($this->ada, 'update', $this->adaPost)->code());
        $this->assertSame(['own' => 1, 'refused' => 1], $this->calls);
    }

    #[Test]
    public function it_drops_the_oldest_perimeters_reached_beyond_its_cap(): void
    {
        $control = ConfiguredPostControl::bind($this->ownInSql());
        $memo = $this->memo();

        foreach (range(1, DecisionMemo::MAX_ENTRIES + 1) as $index) {
            $memo->keepReach($this->reachKey($control, "ability{$index}"), new KeptReach($control, ReachedPerimeters::outsideBoundary()));
        }

        $this->assertSame(DecisionMemo::MAX_ENTRIES, $memo->reachCount());
        $this->assertNull($memo->reach($this->reachKey($control, 'ability1')));
        $this->assertNotNull($memo->reach($this->reachKey($control, 'ability2')));
        $this->assertNotNull($memo->reach($this->reachKey($control, 'ability'.(DecisionMemo::MAX_ENTRIES + 1))));
    }

    private function querying(string $name, bool $answer = true): Closure
    {
        return function (User $user) use ($name, $answer): bool {
            $this->calls[$name] = ($this->calls[$name] ?? 0) + 1;

            return User::query()->whereKey($user->getKey())->exists() && $answer;
        };
    }

    /** @return Collection<int, Post> */
    private function adaPosts(int $count, int $workspace = 1): Collection
    {
        $project = $this->adaPost->project()->firstOrFail();

        foreach (range(1, $count) as $index) {
            Post::factory()->for($project)->for($this->ada, 'author')->createOne(['title' => "Post {$index}", 'workspace_id' => $workspace]);
        }

        return Post::query()->whereBelongsTo($this->ada, 'author')->where('workspace_id', $workspace)->get();
    }

    private function countQueries(callable $decisions): int
    {
        Access::forgetDecisions();
        $this->calls = [];
        $this->queries = 0;

        try {
            $decisions();

            return $this->queries;
        } finally {
            $this->queries = null;
        }
    }

    private function reachKey(ConfiguredPostControl $control, string $ability): ReachKey
    {
        return ReachKey::of($control, AccessContext::of($this->ada, Ability::of($ability))) ?? $this->fail('The reach key is missing.');
    }
}

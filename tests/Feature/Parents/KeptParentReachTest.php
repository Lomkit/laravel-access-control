<?php

namespace Lomkit\Access\Tests\Feature\Parents;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Access;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use PHPUnit\Framework\Attributes\Test;

class KeptParentReachTest extends ParentTestCase
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

        foreach (range(1, 10) as $index) {
            $this->comment($this->releaseNotes, $this->grace, "Comment {$index}", 1);
        }
    }

    #[Test]
    public function it_evaluates_the_allowed_of_the_parent_once_for_every_child_row(): void
    {
        $this->countedGrant('view own posts', ['Ada']);
        $comments = $this->commentsOf('Release notes');

        $queries = $this->countQueries(function () use ($comments): void {
            foreach ($comments as $comment) {
                $this->assertTrue($this->ada->can('view', $comment));
            }
        });

        $this->assertSame(12, $queries);
        $this->assertSame(['view own posts' => 1], $this->calls);
    }

    #[Test]
    public function it_evaluates_the_allowed_of_the_parent_once_after_a_preload(): void
    {
        $this->countedGrant('view own posts', ['Ada']);
        $comments = $this->commentsOf('Release notes');

        $queries = $this->countQueries(function () use ($comments): void {
            Access::preload($this->ada, 'view', $comments);

            foreach ($comments as $comment) {
                $this->assertTrue($this->ada->can('view', $comment));
            }
        });

        $this->assertSame(1, $queries);
        $this->assertSame(['view own posts' => 1], $this->calls);
    }

    #[Test]
    public function it_evaluates_each_parent_of_a_chain_once(): void
    {
        ConfiguredPostControl::bindWithin(Boundary::none(), ThroughParent::belongsTo('project'));
        $this->countedGrant('view client projects', ['Ada']);
        $comments = $this->commentsOf('Release notes');

        $queries = $this->countQueries(function () use ($comments): void {
            Access::preload($this->ada, 'view', $comments);

            foreach ($comments as $comment) {
                $this->assertTrue($this->ada->can('view', $comment));
            }
        });

        $this->assertSame(1, $queries);
        $this->assertSame(['view client projects' => 1], $this->calls);
    }

    /** @param  list<string>  $names */
    private function countedGrant(string $permission, array $names): void
    {
        Gate::define($permission, function (User $reader) use ($permission, $names): bool {
            $this->calls[$permission] = ($this->calls[$permission] ?? 0) + 1;

            return in_array($reader->getAttribute('name'), $names, true);
        });
    }

    /** @return Collection<int, Comment> */
    private function commentsOf(string $title): Collection
    {
        return Comment::query()->whereHas('post', fn ($posts) => $posts->where('title', $title))->get();
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
}

<?php

namespace Lomkit\Access\Tests\Feature\Testing;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Tests\Fixtures\Agencies\Agencies;
use Lomkit\Access\Tests\Fixtures\Agencies\Agency;
use Lomkit\Access\Tests\Fixtures\Agencies\Member;
use Lomkit\Access\Tests\Fixtures\Agencies\Mission;
use Lomkit\Access\Tests\Fixtures\Agencies\MissionControl;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\Fixtures\Testing\AccessTestCase;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;

class ControlAgreesTest extends AccessTestCase
{
    protected User $ada;

    protected Post $own;

    protected Post $other;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog, new Agencies];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $acme = Client::factory()->createOne(['name' => 'Acme']);
        $this->ada = User::factory()->for($acme)->createOne(['name' => 'Ada', 'email' => 'ada@example.com', 'current_workspace_id' => 1]);
        $grace = User::factory()->for($acme)->createOne(['name' => 'Grace', 'email' => 'grace@example.com', 'current_workspace_id' => 1]);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $this->own = Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Release notes', 'workspace_id' => 1]);
        $this->other = Post::factory()->for($website)->for($grace, 'author')->createOne(['title' => 'Roadmap', 'workspace_id' => 1]);
    }

    protected function own(): OwnPerimeter
    {
        return OwnPerimeter::new()
            ->allowed(fn (User $user): bool => true)
            ->query(function (Builder $query, User $user): void {
                $query->where('author_id', $user->getKey());
            });
    }

    #[Test]
    public function it_passes_when_every_decision_path_agrees(): void
    {
        ConfiguredPostControl::bind($this->own()->should(fn (User $user, Post $post): bool => $post->author_id === $user->getKey()));

        $this->assertControlAgrees(PostControl::class, $this->ada, [$this->own, $this->other]);
    }

    #[Test]
    public function it_reports_the_answer_of_each_path_when_a_should_callback_disagrees_with_its_query(): void
    {
        ConfiguredPostControl::bind($this->own()->should(fn (User $user, Post $post): bool => true));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage(sprintf(
            "The decisions of the [%s] control disagree for the [%s] reader with key [%s]:\n- row [%s], ability [view]: row by row [granted], preload [granted], withAbilities [granted], controlled() [not listed]\n",
            PostControl::class,
            User::class,
            $this->ada->getKey(),
            $this->other->getKey(),
        ));

        $this->assertControlAgrees(PostControl::class, $this->ada, [$this->own, $this->other], ['view']);
    }

    #[Test]
    public function it_names_every_ability_and_row_that_disagree(): void
    {
        ConfiguredPostControl::bind($this->own()->should(fn (User $user, Post $post): bool => true));

        try {
            $this->assertControlAgrees(PostControl::class, $this->ada, [$this->own, $this->other]);
            $this->fail('The disagreement was not reported.');
        } catch (AssertionFailedError $failure) {
            $this->assertStringContainsString(sprintf('- row [%s], ability [view]', $this->other->getKey()), $failure->getMessage());
            $this->assertStringNotContainsString(sprintf('- row [%s], ability', $this->own->getKey()), $failure->getMessage());
            $this->assertStringContainsString('make them describe the same rows, or drop the should callback', $failure->getMessage());
        }
    }

    #[Test]
    public function it_reports_a_refusal_without_a_code_when_no_policy_decides(): void
    {
        $north = Agency::factory()->createOne(['name' => 'North']);
        $linus = Member::factory()->for($north)->createOne(['name' => 'Linus', 'email' => 'linus@example.com']);
        $mission = Mission::factory()->for($north)->for($linus)->createOne(['title' => 'Survey']);
        Gate::define('view assigned missions', fn (Member $member): bool => true);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage(sprintf('- row [%s], ability [view]: row by row [denied without a code], preload [denied without a code], withAbilities [denied without a code], controlled() [listed]', $mission->getKey()));

        $this->assertControlAgrees(MissionControl::class, $linus, [$mission], ['view']);
    }

    #[Test]
    public function it_leaves_no_decision_behind(): void
    {
        ConfiguredPostControl::bind($this->own());

        $this->assertControlAgrees(PostControl::class, $this->ada, [$this->own, $this->other]);

        $queries = $this->queriesWhile(fn () => $this->ada->can('update', $this->own));
        $this->assertCount(1, $queries);
    }

    #[Test]
    public function it_refuses_an_empty_list_of_rows(): void
    {
        ConfiguredPostControl::bind($this->own());

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage(sprintf('assertControlAgrees() received no row for the [%s] control', PostControl::class));

        $this->assertControlAgrees(PostControl::class, $this->ada, []);
    }

    #[Test]
    public function it_refuses_an_unsaved_row(): void
    {
        ConfiguredPostControl::bind($this->own());

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage(sprintf('assertControlAgrees() received an unsaved [%s] row', Post::class));

        $this->assertControlAgrees(PostControl::class, $this->ada, [new Post]);
    }

    #[Test]
    public function it_refuses_a_row_of_another_model(): void
    {
        ConfiguredPostControl::bind($this->own());

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage(sprintf('assertControlAgrees() received a row that is not a [%s] model', Post::class));

        $this->assertControlAgrees(PostControl::class, $this->ada, [$this->ada]);
    }

    /** @return list<string> */
    protected function queriesWhile(callable $decisions): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $decisions();
        DB::disableQueryLog();

        return array_column(DB::getQueryLog(), 'query');
    }
}

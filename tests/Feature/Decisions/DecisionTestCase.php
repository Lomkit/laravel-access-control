<?php

namespace Lomkit\Access\Tests\Feature\Decisions;

use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Decisions\DecisionMemo;
use Lomkit\Access\Decisions\Verdict;
use Lomkit\Access\Decisions\VerdictRecorder;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\PackageTestCase;

abstract class DecisionTestCase extends PackageTestCase
{
    protected User $ada;

    protected User $grace;

    protected Post $adaPost;

    protected Post $teamPost;

    protected Post $otherClientPost;

    protected Post $elsewhere;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $acme = Client::factory()->createOne(['name' => 'Acme']);
        $globex = Client::factory()->createOne(['name' => 'Globex']);
        $this->ada = User::factory()->for($acme)->createOne(['name' => 'Ada', 'email' => 'ada@example.com', 'current_workspace_id' => 1]);
        $this->grace = User::factory()->for($acme)->createOne(['name' => 'Grace', 'email' => 'grace@example.com', 'current_workspace_id' => 1]);
        $linus = User::factory()->for($globex)->createOne(['name' => 'Linus', 'email' => 'linus@example.com', 'current_workspace_id' => 1]);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $portal = Project::factory()->for($globex)->createOne(['name' => 'Portal']);
        $this->adaPost = Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Release notes', 'workspace_id' => 1]);
        $this->teamPost = Post::factory()->for($website)->for($this->grace, 'author')->createOne(['title' => 'Roadmap', 'workspace_id' => 1]);
        $this->otherClientPost = Post::factory()->for($portal)->for($linus, 'author')->createOne(['title' => 'Pricing', 'workspace_id' => 1]);
        $this->elsewhere = Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Elsewhere', 'workspace_id' => 2]);
    }

    protected function ownInSql(?Closure $allowed = null): OwnPerimeter
    {
        return OwnPerimeter::new()
            ->allowed($allowed ?? fn (User $user): bool => true)
            ->query(function (Builder $query, User $user): void {
                $query->where('author_id', $user->getKey());
            });
    }

    protected function ownInMemory(): OwnPerimeter
    {
        return $this->ownInSql()->should(fn (User $user, Post $post): bool => $post->author_id === $user->getKey());
    }

    protected function clientInSql(?Closure $allowed = null): ClientPerimeter
    {
        return ClientPerimeter::new()
            ->allowed($allowed ?? fn (User $user): bool => true)
            ->query(function (Builder $query, User $user): void {
                $query->whereHas('project', fn (Builder $projects) => $projects->where('client_id', $user->client_id));
            });
    }

    protected function refusal(): Closure
    {
        return fn (User $user): bool => false;
    }

    protected function inspect(User $reader, string $ability, mixed $subject): Response
    {
        return Gate::forUser($reader)->inspect($ability, $subject);
    }

    /** @return list<string> */
    protected function queriesWhile(callable $decisions): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $decisions();
        } finally {
            DB::disableQueryLog();
        }

        return array_map(
            fn (string $query): string => str_replace(['"', '`'], '', $query),
            array_column(DB::getQueryLog(), 'query'),
        );
    }

    protected function record(User $reader, string $ability, Post $post, Verdict $verdict): void
    {
        VerdictRecorder::for(Access::controlFor(Post::class), $reader, Ability::of($ability), $this->app->make(DecisionMemo::class))
            ->record(Post::class, $post->getKey(), $verdict);
    }

    protected function memo(): DecisionMemo
    {
        return $this->app->make(DecisionMemo::class);
    }
}

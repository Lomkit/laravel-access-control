<?php

namespace Lomkit\Access\Tests\Feature\Parents;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Access;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Testing\AccessTestCase;

abstract class ParentTestCase extends AccessTestCase
{
    protected User $ada;

    protected User $grace;

    protected User $linus;

    protected User $root;

    protected Post $releaseNotes;

    protected Post $roadmap;

    protected Post $archive;

    protected Post $elsewhere;

    protected Post $pricing;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->discoverControls(['Controls', 'Projects']);
        Access::registerPolicies();

        $acme = Client::factory()->createOne(['name' => 'Acme']);
        $globex = Client::factory()->createOne(['name' => 'Globex']);
        $this->ada = User::factory()->for($acme)->createOne(['name' => 'Ada', 'email' => 'ada@example.com', 'current_workspace_id' => 1]);
        $this->grace = User::factory()->for($acme)->createOne(['name' => 'Grace', 'email' => 'grace@example.com', 'current_workspace_id' => 1]);
        $this->linus = User::factory()->for($globex)->createOne(['name' => 'Linus', 'email' => 'linus@example.com', 'current_workspace_id' => 1]);
        $this->root = User::factory()->for($globex)->createOne(['name' => 'Root', 'email' => 'root@example.com', 'current_workspace_id' => 2]);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $portal = Project::factory()->for($globex)->createOne(['name' => 'Portal']);
        $this->releaseNotes = $this->postIn($website, $this->ada, 'Release notes', 1);
        $this->roadmap = $this->postIn($website, $this->grace, 'Roadmap', 1);
        $this->archive = $this->postIn($website, $this->ada, 'Archive', 1, '2026-01-01 00:00:00');
        $this->elsewhere = $this->postIn($website, $this->ada, 'Elsewhere', 2);
        $this->pricing = $this->postIn($portal, $this->linus, 'Pricing', 1);
        $this->comment($this->releaseNotes, $this->grace, 'Ship it', 1);
        $this->comment($this->releaseNotes, $this->linus, 'Typo in the title', 1);
        $this->comment($this->roadmap, $this->ada, 'Add search', 1);
        $this->comment($this->archive, $this->grace, 'Old news', 1);
        $this->comment($this->elsewhere, $this->grace, 'Wrong workspace', 2);
        $this->comment($this->pricing, $this->ada, 'Too expensive', 1);
        $this->comment($this->pricing, $this->linus, 'Agreed', 2);
    }

    /** @param  list<string>  $directories */
    protected function discoverControls(array $directories): void
    {
        Access::discoverControlsIn(array_map(fn (string $directory): string => dirname(__DIR__, 2)."/Fixtures/Inheritance/{$directory}", $directories));
    }

    protected function postIn(Project $project, User $author, string $title, int $workspace, ?string $archivedAt = null): Post
    {
        return Post::factory()
            ->for($project)
            ->for($author, 'author')
            ->createOne(['title' => $title, 'workspace_id' => $workspace, 'archived_at' => $archivedAt]);
    }

    protected function comment(Post $post, User $author, string $body, int $workspace): Comment
    {
        return Comment::factory()
            ->for($post)
            ->for($author, 'author')
            ->createOne(['body' => $body, 'workspace_id' => $workspace]);
    }

    /** @param  array<string, list<string>>  $permissions */
    protected function grant(array $permissions): void
    {
        foreach ($permissions as $permission => $names) {
            Gate::define($permission, fn (User $reader): bool => in_array($reader->getAttribute('name'), $names, true));
        }
    }

    protected function commentNamed(string $body): Comment
    {
        return Comment::query()->where('body', $body)->firstOrFail();
    }

    /** @return list<string> */
    protected function visibleComments(?User $reader): array
    {
        return Comment::controlledFor($reader)
            ->orderBy('id')
            ->pluck('body')
            ->all();
    }

    /** @return list<string> */
    protected function bodiesFor(User $reader, string $ability): array
    {
        $bodies = [];

        foreach (Comment::query()->orderBy('id')->get() as $comment) {
            if (Gate::forUser($reader)->allows($ability, $comment)) {
                $bodies[] = $comment->body;
            }
        }

        return $bodies;
    }

    /**
     * @param  callable(): mixed  $callback
     * @return list<string>
     */
    protected function queriesWhile(callable $callback): array
    {
        Access::forgetDecisions();

        return $this->queriesWithoutForgetting($callback);
    }

    /**
     * @param  callable(): mixed  $callback
     * @return list<string>
     */
    protected function queriesWithoutForgetting(callable $callback): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $callback();

        DB::disableQueryLog();

        return array_map(fn (array $query): string => $query['query'], DB::getQueryLog());
    }

    protected function draftOn(?Model $post, User $author): Comment
    {
        $draft = new Comment(['author_id' => $author->getKey(), 'body' => 'Draft']);

        if ($post !== null) {
            $draft->setAttribute('post_id', $post->getKey());
        }

        return $draft;
    }
}

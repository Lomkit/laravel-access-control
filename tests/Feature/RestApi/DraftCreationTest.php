<?php

namespace Lomkit\Access\Tests\Feature\RestApi;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Testing\TestResponse;
use Lomkit\Access\Ability;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('rest-api')]
class DraftCreationTest extends RestApiTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->grant(['view own posts' => ['Ada'], 'create own posts' => ['Ada']]);
        $this->actingAs($this->ada);
    }

    #[Test]
    public function it_refuses_a_creation_through_a_perimeter_checked_in_sql(): void
    {
        ConfiguredPostControl::bind($this->ownPosts());

        $this->create($this->ada)->assertForbidden();

        $this->assertSame(5, Post::query()->count());
    }

    #[Test]
    public function it_creates_a_row_whose_draft_lands_in_a_perimeter_with_a_should_callback(): void
    {
        ConfiguredPostControl::bind($this->ownPosts()->should(fn (User $user, Post $post): bool => $post->author_id === $user->getKey()));

        $this->create($this->grace)->assertForbidden();
        $this->create($this->ada)->assertOk();

        $this->assertSame(1, Post::query()->where('title', 'Draft')->count());
    }

    private function ownPosts(): OwnPerimeter
    {
        return OwnPerimeter::new()
            ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} own posts"))
            ->query(function (Builder $query, User $user): void {
                $query->whereBelongsTo($user, 'author');
            });
    }

    private function create(User $author): TestResponse
    {
        return $this->postJson('/posts/mutate', ['mutate' => [[
            'operation' => 'create',
            'attributes' => ['title' => 'Draft', 'project_id' => $this->releaseNotes->project_id, 'author_id' => $author->getKey(), 'workspace_id' => 1],
        ]]]);
    }
}

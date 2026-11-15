<?php

namespace Lomkit\Access\Tests\Feature\RestApi;

use Illuminate\Testing\TestResponse;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('rest-api')]
class RestrictedWritesTest extends RestApiTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->grant(['view client posts' => ['Ada'], 'update client posts' => ['Ada'], 'delete client posts' => ['Ada']]);
        $this->actingAs($this->ada);
    }

    #[Test]
    public function it_does_not_find_a_hidden_row_to_update(): void
    {
        $this->mutate($this->pricing, 'Hidden')->assertUnprocessable()->assertJsonValidationErrors('mutate.0.key');

        $this->assertSame('Pricing', $this->pricing->refresh()->title);
    }

    #[Test]
    public function it_updates_a_row_that_the_control_shows(): void
    {
        $this->mutate($this->roadmap, 'Next roadmap')->assertOk();

        $this->assertSame('Next roadmap', $this->roadmap->refresh()->title);
    }

    #[Test]
    public function it_does_not_find_a_hidden_row_to_destroy(): void
    {
        $this->deleteJson('/posts', ['resources' => [$this->pricing->getKey(), $this->elsewhere->getKey()]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['resources.0', 'resources.1']);

        $this->assertSame(5, Post::query()->count());
    }

    private function mutate(Post $post, string $title): TestResponse
    {
        return $this->postJson('/posts/mutate', ['mutate' => [['operation' => 'update', 'key' => $post->getKey(), 'attributes' => ['title' => $title]]]]);
    }
}

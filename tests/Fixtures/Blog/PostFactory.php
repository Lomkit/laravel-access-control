<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Post> */
#[UseModel(Post::class)]
class PostFactory extends Factory
{
    #[\Override]
    public function definition(): array
    {
        return [
            'project_id' => ProjectFactory::new(),
            'author_id' => UserFactory::new(),
            'title' => 'Release notes',
            'published' => true,
            'workspace_id' => 1,
        ];
    }
}

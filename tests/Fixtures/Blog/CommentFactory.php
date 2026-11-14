<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Comment> */
#[UseModel(Comment::class)]
class CommentFactory extends Factory
{
    #[\Override]
    public function definition(): array
    {
        return [
            'post_id' => PostFactory::new(),
            'author_id' => UserFactory::new(),
            'body' => 'Looks good to me',
        ];
    }
}

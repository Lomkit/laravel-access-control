<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Article> */
#[UseModel(Article::class)]
class ArticleFactory extends Factory
{
    #[\Override]
    public function definition(): array
    {
        return [
            'author_id' => null,
            'project_id' => null,
            'title' => 'Scout primer',
            'body' => 'Index the rows that a user may read.',
            'featured' => false,
            'workspace_id' => 1,
        ];
    }
}

<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
#[UseModel(Project::class)]
class ProjectFactory extends Factory
{
    #[\Override]
    public function definition(): array
    {
        return [
            'client_id' => ClientFactory::new(),
            'name' => 'Website',
        ];
    }
}

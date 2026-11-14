<?php

namespace Lomkit\Access\Tests\Feature\Decisions;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Access;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\User;

abstract class BatchTestCase extends DecisionTestCase
{
    protected Post $archived;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->archived = Post::factory()
            ->for($this->adaPost->project)
            ->for($this->ada, 'author')
            ->createOne(['title' => 'Archive', 'workspace_id' => 1, 'archived_at' => '2026-01-01 00:00:00']);
    }

    /** @return list<Post> */
    protected function everyPost(): array
    {
        return Post::uncontrolled()->orderBy('id')->get()->all();
    }

    /**
     * @param  list<string>  $abilities
     * @param  iterable<Model>  $rows
     * @return array<string, array<string, string>>
     */
    protected function answers(User $reader, array $abilities, iterable $rows): array
    {
        $answers = [];

        foreach ($rows as $row) {
            foreach ($abilities as $ability) {
                $response = $this->inspect($reader, $ability, $row);
                $answers[$row::class.'#'.$row->getKey()][$ability] = $response->allowed() ? 'granted' : (string) $response->code();
            }
        }

        return $answers;
    }

    /**
     * @param  list<string>  $abilities
     * @param  iterable<Model>  $rows
     * @return array<string, array<string, string>>
     */
    protected function rowByRow(User $reader, array $abilities, iterable $rows): array
    {
        Access::forgetDecisions();

        return $this->answers($reader, $abilities, $rows);
    }
}

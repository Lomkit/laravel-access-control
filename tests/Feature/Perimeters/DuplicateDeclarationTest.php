<?php

namespace Lomkit\Access\Tests\Feature\Perimeters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Ability;
use Lomkit\Access\Exceptions\DuplicatePerimeterCallbackException;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class DuplicateDeclarationTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog];
    }

    protected static function searchKeys(Perimeter $perimeter, string $name): Perimeter
    {
        return $perimeter->searchKeys(
            name: $name,
            row: fn (Post $post): mixed => $post->project?->client_id,
            reader: fn (User $user): mixed => $user->client_id,
            load: ['project:id,client_id'],
        );
    }

    /** @return array<string, array{Closure(Perimeter): Perimeter, string}> */
    public static function declarations(): array
    {
        return [
            'allowed' => [fn (Perimeter $perimeter): Perimeter => $perimeter->allowed(fn (User $user): bool => true), 'allowed'],
            'should' => [fn (Perimeter $perimeter): Perimeter => $perimeter->should(fn (User $user, Post $post): bool => true), 'should'],
            'query' => [fn (Perimeter $perimeter): Perimeter => $perimeter->query(function (Builder $query, User $user): void {
                $query->whereRelation('project', 'client_id', $user->client_id);
            }), 'query'],
            'searchKeys' => [fn (Perimeter $perimeter): Perimeter => self::searchKeys($perimeter, 'client'), 'searchKeys'],
        ];
    }

    /** @param  Closure(Perimeter): Perimeter  $declare */
    #[Test]
    #[DataProvider('declarations')]
    public function it_refuses_a_second_declaration_of_the_same_callback(Closure $declare, string $callback): void
    {
        $perimeter = $declare(ClientPerimeter::new());

        $this->expectException(DuplicatePerimeterCallbackException::class);
        $this->expectExceptionMessage(sprintf('The [%s] perimeter declares %s() twice', ClientPerimeter::class, $callback));

        $declare($perimeter);
    }

    #[Test]
    public function it_keeps_the_first_declaration_when_it_refuses_the_second(): void
    {
        $perimeter = ClientPerimeter::new()->allowed(fn (User $user): bool => false);

        try {
            $perimeter->allowed(fn (User $user): bool => true);
            $this->fail('The second allowed() was not refused.');
        } catch (DuplicatePerimeterCallbackException) {
            $this->assertFalse($perimeter->admits(new User, Ability::view(), new Post));
        }
    }

    #[Test]
    public function it_refuses_search_keys_declared_again_under_another_name(): void
    {
        $perimeter = self::searchKeys(ClientPerimeter::new(), 'client');

        $this->expectException(DuplicatePerimeterCallbackException::class);
        $this->expectExceptionMessage('drop the reindexWhen() rules declared after them');

        self::searchKeys($perimeter, 'customer');
    }

    #[Test]
    public function it_still_reindexes_after_a_single_declaration_of_search_keys(): void
    {
        $perimeter = self::searchKeys(ClientPerimeter::new(), 'client')
            ->reindexWhen(Project::class, changed: ['client_id'], rows: fn (Project $project) => $project->posts());

        $this->assertSame(Project::class, $perimeter->declaredSearchKeys()?->reindexes()->for(Project::class)?->model());
        $this->assertSame('client', $perimeter->declaredSearchKeys()->name());
    }

    #[Test]
    public function it_declares_each_callback_once_in_any_order(): void
    {
        $perimeter = self::searchKeys(ClientPerimeter::new(), 'client')
            ->query(function (Builder $query, User $user): void {
                $query->whereRelation('project', 'client_id', $user->client_id);
            })
            ->should(fn (User $user, Post $post): bool => true)
            ->allowed(fn (User $user): bool => true);

        $this->assertTrue($perimeter->checksInMemory());
        $this->assertNotNull($perimeter->declaredSearchKeys());
    }
}

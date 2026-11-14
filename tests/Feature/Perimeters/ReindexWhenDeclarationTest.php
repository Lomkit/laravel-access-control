<?php

namespace Lomkit\Access\Tests\Feature\Perimeters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\DuplicateReindexException;
use Lomkit\Access\Exceptions\ImpurePerimetersException;
use Lomkit\Access\Exceptions\InvalidReindexAttributesException;
use Lomkit\Access\Exceptions\InvalidReindexModelException;
use Lomkit\Access\Exceptions\InvalidReindexRowsException;
use Lomkit\Access\Exceptions\ReindexWithoutSearchKeysException;
use Lomkit\Access\Exceptions\UnwatchableReindexException;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use stdClass;

class ReindexWhenDeclarationTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog];
    }

    protected function client(): ClientPerimeter
    {
        return ClientPerimeter::new()
            ->allowed(fn (User $user): bool => true)
            ->query(function (Builder $query, User $user): void {
                $query->whereRelation('project', 'client_id', $user->client_id);
            })
            ->searchKeys(
                name: 'client',
                row: fn (Post $post): mixed => $post->project?->client_id,
                reader: fn (User $user): mixed => $user->client_id,
                load: ['project:id,client_id'],
            );
    }

    #[Test]
    public function it_declares_the_model_the_attributes_and_the_rows_to_reindex(): void
    {
        $perimeter = $this->client()->reindexWhen(Project::class, changed: ['client_id', 'client_id'], rows: fn (Project $project) => $project->posts());

        $reindex = $perimeter->declaredSearchKeys()?->reindexes()->for(Project::class);

        $this->assertSame(Project::class, $reindex?->model());
        $this->assertSame(['client_id'], $reindex->changed());
    }

    #[Test]
    public function it_accepts_several_models_on_one_perimeter(): void
    {
        $perimeter = $this->client()
            ->reindexWhen(Project::class, changed: ['client_id'], rows: fn (Project $project) => $project->posts())
            ->reindexWhen(Client::class, changed: ['name'], rows: fn (Client $client) => Post::query()->whereRelation('project', 'client_id', $client->getKey()));

        $this->assertSame(
            [Project::class, Client::class],
            array_map(fn ($reindex): string => $reindex->model(), $perimeter->declaredSearchKeys()?->reindexes()->all() ?? []),
        );
    }

    #[Test]
    public function it_leaves_the_perimeter_it_is_called_on_unchanged(): void
    {
        $perimeter = $this->client();

        $perimeter->reindexWhen(Project::class, changed: ['client_id'], rows: fn (Project $project) => $project->posts());

        $this->assertSame([], $perimeter->declaredSearchKeys()?->reindexes()->all());
    }

    /** @return array<string, array{string}> */
    public static function notModels(): array
    {
        return [
            'an unknown class' => ['App\Models\Missing'],
            'a class that is not a model' => [stdClass::class],
            'the model base class' => [Model::class],
            'empty' => [''],
        ];
    }

    #[Test]
    #[DataProvider('notModels')]
    public function it_refuses_a_class_that_is_not_an_eloquent_model(string $model): void
    {
        $this->expectException(InvalidReindexModelException::class);
        $this->expectExceptionMessage(sprintf('The [%s] perimeter reindexes its rows when [%s] changes, which is not an Eloquent model class.', ClientPerimeter::class, $model));

        $this->client()->reindexWhen($model, changed: ['client_id'], rows: fn (Project $project) => $project->posts());
    }

    #[Test]
    public function it_refuses_an_empty_list_of_attributes(): void
    {
        $this->expectException(InvalidReindexAttributesException::class);
        $this->expectExceptionMessage(sprintf('The [%s] perimeter reindexes its rows when [%s] changes without naming an attribute.', ClientPerimeter::class, Project::class));

        $this->client()->reindexWhen(Project::class, changed: [], rows: fn (Project $project) => $project->posts());
    }

    #[Test]
    public function it_refuses_a_blank_attribute_name(): void
    {
        $this->expectException(InvalidReindexAttributesException::class);
        $this->expectExceptionMessage(sprintf('The [%s] perimeter reindexes its rows when [%s] changes and lists a blank attribute name in changed:.', ClientPerimeter::class, Project::class));

        $this->client()->reindexWhen(Project::class, changed: ['client_id', ' '], rows: fn (Project $project) => $project->posts());
    }

    #[Test]
    public function it_refuses_a_perimeter_without_search_keys(): void
    {
        $this->expectException(ReindexWithoutSearchKeysException::class);
        $this->expectExceptionMessage(sprintf('The [%s] perimeter reindexes its rows when [%s] changes but declares no search keys', ClientPerimeter::class, Project::class));

        ClientPerimeter::new()
            ->allowed(fn (User $user): bool => true)
            ->reindexWhen(Project::class, changed: ['client_id'], rows: fn (Project $project) => $project->posts());
    }

    #[Test]
    public function it_refuses_a_rows_callback_that_does_not_accept_the_model(): void
    {
        $this->expectException(InvalidReindexRowsException::class);
        $this->expectExceptionMessage(sprintf('The rows callback of the [%s] perimeter does not accept a [%s], the model it reindexes for.', ClientPerimeter::class, Project::class));

        $this->client()->reindexWhen(Project::class, changed: ['client_id'], rows: fn (Comment $comment) => Post::query());
    }

    #[Test]
    public function it_accepts_a_rows_callback_typed_with_a_parent_class_or_untyped(): void
    {
        $typed = $this->client()->reindexWhen(Project::class, changed: ['client_id'], rows: fn (Model $project) => Post::query());
        $untyped = $this->client()->reindexWhen(Project::class, changed: ['client_id'], rows: fn ($project) => Post::query());

        $this->assertNotNull($typed->declaredSearchKeys()?->reindexes()->for(Project::class));
        $this->assertNotNull($untyped->declaredSearchKeys()?->reindexes()->for(Project::class));
    }

    #[Test]
    public function it_refuses_two_declarations_for_the_same_model_on_one_perimeter(): void
    {
        $this->expectException(DuplicateReindexException::class);
        $this->expectExceptionMessage(sprintf('The [%s] perimeter declares reindexWhen() twice for [%s].', ClientPerimeter::class, Project::class));

        $this->client()
            ->reindexWhen(Project::class, changed: ['client_id'], rows: fn (Project $project) => $project->posts())
            ->reindexWhen(Project::class, changed: ['name'], rows: fn (Project $project) => $project->posts());
    }

    #[Test]
    public function it_compares_the_reindex_declarations_in_the_shape_of_a_perimeter(): void
    {
        $declare = fn (array $changed): Perimeter => $this->client()->reindexWhen(Project::class, changed: $changed, rows: fn (Project $project) => $project->posts());

        $this->assertTrue($declare(['client_id'])->shape()->matches($declare(['client_id'])->shape()));
        $this->assertFalse($declare(['client_id'])->shape()->matches($declare(['name'])->shape()));
        $this->assertFalse($declare(['client_id'])->shape()->matches($this->client()->shape()));
    }

    #[Test]
    public function it_detects_an_impure_reindex_declaration(): void
    {
        $control = new class extends PostControl
        {
            private int $built = 0;

            #[\Override]
            protected function perimeters(): array
            {
                $changed = ++$this->built === 1 ? ['client_id'] : ['name'];

                return [
                    ClientPerimeter::new()
                        ->allowed(fn (User $user): bool => true)
                        ->searchKeys('client', fn (Post $post): int => 1, fn (User $user): int => 1)
                        ->reindexWhen(Project::class, changed: $changed, rows: fn (Project $project) => $project->posts()),
                ];
            }
        };

        $control->declaration();

        $this->expectException(ImpurePerimetersException::class);

        $control->verifyPerimeters();
    }

    #[Test]
    public function it_refuses_a_control_that_reindexes_without_being_discovered(): void
    {
        ConfiguredPostControl::bind(
            $this->client()->reindexWhen(Project::class, changed: ['client_id'], rows: fn (Project $project) => $project->posts()),
        );

        $this->expectException(UnwatchableReindexException::class);
        $this->expectExceptionMessage(sprintf('The [%s] control declares reindexWhen(), but it is not in the discovered directories', PostControl::class));

        Access::control(PostControl::class);
    }
}

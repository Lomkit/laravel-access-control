<?php

namespace Lomkit\Access\Tests\Feature\RestApi;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Exceptions\InvalidRestReaderException;
use Lomkit\Access\Integrations\RestApi\AccessControlExtension;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\Fixtures\RestApi\CommentResource;
use Lomkit\Access\Tests\Fixtures\RestApi\RestrictionOnlyExtension;
use Lomkit\Rest\Authorization\PendingChecks;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('rest-api')]
class RowCheckPreparationTest extends RestApiTestCase
{
    private const array GATES = ['view', 'update', 'delete'];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->grant([
            'view client posts' => ['Ada'],
            'view own comments' => ['Ada'],
            'update own comments' => ['Ada'],
            'delete own comments' => ['Ada'],
        ]);
    }

    #[Test]
    public function it_decides_the_gates_of_a_page_with_one_query_per_ability_whatever_its_size(): void
    {
        foreach ([2, 8] as $added) {
            $this->addComments($added);

            [$rows, $verdicts] = $this->gatedSearch();

            $this->assertCount(count(self::GATES), $verdicts);
            $this->assertSame($this->gatesOf($rows), $this->policyAnswers($rows));
        }
    }

    #[Test]
    public function it_runs_one_query_per_row_and_ability_without_the_preparation(): void
    {
        config(['rest.extensions' => [RestrictionOnlyExtension::class], 'rest.disabled_extensions' => [AccessControlExtension::class]]);
        $this->addComments(8);

        [$rows, $verdicts] = $this->gatedSearch();

        $this->assertCount(count($rows) * count(self::GATES), $verdicts);
        $this->assertSame($this->gatesOf($rows), $this->policyAnswers($rows));
    }

    #[Test]
    public function it_prepares_a_gate_that_the_policy_maps_through_the_control(): void
    {
        $this->grant(['approve own posts' => ['Ada']]);
        ConfiguredPostControl::bind($this->clientPosts(), $this->ownPosts());

        $rows = [];
        $verdicts = $this->verdictQueriesWhile(function () use (&$rows): void {
            $rows = $this->search('posts', $this->ada, ['gates' => ['approve']])->assertOk()->json('data');
        });

        $this->assertCount(1, $verdicts);
        $this->assertSame([true, false, false], [Gate::forUser($this->ada)->allows('approve', $this->releaseNotes), Gate::forUser($this->ada)->allows('approve', $this->roadmap), Gate::forUser($this->ada)->allows('approve', $this->archive)]);
        $this->assertSame(
            ['Release notes' => true, 'Roadmap' => false, 'Archive' => false],
            array_combine(array_column($rows, 'title'), array_map(fn (array $row): bool => $row['gates']['authorized_to_approve']['allowed'], $rows)),
        );
    }

    #[Test]
    public function it_prepares_the_controlled_rows_only_and_changes_none(): void
    {
        $comments = Comment::query()->with('post')->orderBy('id')->get();
        $rows = new Collection([...$comments->all(), ...Client::query()->get()->all()]);
        $before = $this->snapshot($rows);

        $verdicts = $this->verdictQueriesWhile(fn () => (new AccessControlExtension)->prepareRowChecks(new PendingChecks(new CommentResource, 'update', $rows, $this->ada)));

        $this->assertCount(1, $verdicts);
        $this->assertSame($before, $this->snapshot($rows));
        $this->assertSame([], $this->queriesWithoutForgetting(fn () => Gate::forUser($this->ada)->allows('update', $comments->first())));
    }

    #[Test]
    public function it_prepares_nothing_for_a_guest_or_rows_without_control(): void
    {
        $comments = Comment::query()->get();
        $clients = Client::query()->get();
        $extension = new AccessControlExtension;

        $this->assertSame([], $this->queriesWhile(fn () => $extension->prepareRowChecks(new PendingChecks(new CommentResource, 'update', $comments, null))));
        $this->assertSame([], $this->queriesWhile(fn () => $extension->prepareRowChecks(new PendingChecks(new CommentResource, 'update', $clients, $this->ada))));
    }

    #[Test]
    public function it_refuses_to_prepare_for_a_user_that_is_not_an_eloquent_model(): void
    {
        $this->expectException(InvalidRestReaderException::class);

        (new AccessControlExtension)->prepareRowChecks(new PendingChecks(new CommentResource, 'view', Comment::query()->get(), new GenericUser(['id' => 1])));
    }

    private function clientPosts(): ClientPerimeter
    {
        return ClientPerimeter::new()
            ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} client posts"))
            ->query(fn (Builder $query, User $user): Builder => $query->whereHas('project', fn (Builder $projects): Builder => $projects->where('client_id', $user->client_id)));
    }

    private function ownPosts(): OwnPerimeter
    {
        return OwnPerimeter::new()
            ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} own posts"))
            ->query(fn (Builder $query, User $user): Builder => $query->where('author_id', $user->getKey()));
    }

    private function addComments(int $count): void
    {
        for ($index = 0; $index < $count; $index++) {
            $this->comment($this->roadmap, $this->ada, "Extra {$count}.{$index}", 1);
        }
    }

    /** @return array{list<array<string, mixed>>, list<string>} */
    private function gatedSearch(): array
    {
        $this->actingAs($this->ada);
        $rows = [];

        $verdicts = $this->verdictQueriesWhile(function () use (&$rows): void {
            $rows = $this->search('comments', $this->ada, ['gates' => self::GATES])->assertOk()->json('data');
        });

        return [$rows, $verdicts];
    }

    /** @return list<string> */
    private function verdictQueriesWhile(callable $callback): array
    {
        return array_values(array_filter($this->queriesWhile($callback), fn (string $query): bool => str_contains($query, 'access_verdict')));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, array<string, bool>>
     */
    private function gatesOf(array $rows): array
    {
        $gates = [];

        foreach ($rows as $row) {
            foreach (self::GATES as $gate) {
                $gates[$row['id']][$gate] = $row['gates']["authorized_to_{$gate}"]['allowed'];
            }
        }

        return $gates;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, array<string, bool>>
     */
    private function policyAnswers(array $rows): array
    {
        $answers = [];

        foreach (Comment::query()->whereKey(array_column($rows, 'id'))->orderBy('id')->get() as $comment) {
            foreach (self::GATES as $gate) {
                $answers[$comment->getKey()][$gate] = Gate::forUser($this->ada)->allows($gate, $comment);
            }
        }

        return $answers;
    }

    /**
     * @param  Collection<int, Model>  $rows
     * @return list<array{array<string, mixed>, array<string, int>, bool}>
     */
    private function snapshot(Collection $rows): array
    {
        return $rows->map(fn (Model $row): array => [$row->getAttributes(), array_map(spl_object_id(...), array_filter($row->getRelations())), $row->exists])->all();
    }
}

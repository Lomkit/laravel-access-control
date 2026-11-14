<?php

namespace Lomkit\Access\Tests\Feature\Decisions;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Decisions\Verdict;
use Lomkit\Access\Exceptions\InvalidDecisionContextException;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class DecisionContextTest extends DecisionTestCase
{
    #[Test]
    public function it_has_an_empty_decision_context_without_a_resolver(): void
    {
        $this->assertSame([], AccessContext::of($this->ada, Ability::view())->decisionContext());
    }

    #[Test]
    public function it_sorts_the_decision_context_by_key(): void
    {
        Access::decisionContextUsing(fn (): array => ['region' => 'eu', 'brand' => 7, 'beta' => true, 'campaign' => null]);

        $this->assertSame(
            ['beta' => true, 'brand' => 7, 'campaign' => null, 'region' => 'eu'],
            AccessContext::of($this->ada, Ability::view())->decisionContext(),
        );
    }

    #[Test]
    public function it_evaluates_the_resolver_each_time_a_context_is_built(): void
    {
        $brand = 1;
        Access::decisionContextUsing(function () use (&$brand): array {
            return ['brand' => $brand];
        });

        $first = AccessContext::of($this->ada, Ability::view());
        $brand = 2;

        $this->assertSame(['brand' => 1], $first->decisionContext());
        $this->assertSame(['brand' => 2], AccessContext::of($this->ada, Ability::view())->decisionContext());
    }

    #[Test]
    public function it_gives_the_decision_context_to_the_hooks_of_a_perimeter(): void
    {
        Access::decisionContextUsing(fn (): array => ['brand' => 7]);
        ConfiguredPostControl::bind(new class extends Perimeter
        {
            #[\Override]
            protected function allows(AccessContext $context): bool
            {
                return $context->decisionContext() === ['brand' => 7];
            }

            #[\Override]
            protected function scope(Builder $query, AccessContext $context): void
            {
                $query->where('author_id', $context->reader()->getKey());
            }
        });

        $this->assertTrue($this->ada->can('update', $this->adaPost));

        Access::decisionContextUsing(fn (): array => ['brand' => 8]);

        $this->assertSame('no_perimeter', $this->inspect($this->ada, 'update', $this->adaPost)->code());
    }

    #[Test]
    public function it_files_a_decision_under_the_context_it_was_made_in(): void
    {
        $brand = 1;
        Access::decisionContextUsing(function () use (&$brand): array {
            return ['brand' => $brand];
        });
        ConfiguredPostControl::bind($this->ownInSql());
        $this->record($this->ada, 'update', $this->teamPost, Verdict::Granted);

        $this->assertTrue($this->ada->can('update', $this->teamPost));

        $brand = 2;

        $queries = $this->queriesWhile(function (): void {
            $this->assertFalse($this->ada->can('update', $this->teamPost));
        });
        $this->assertCount(1, $queries);
    }

    #[Test]
    public function it_tells_apart_context_values_of_different_types(): void
    {
        $brand = true;
        Access::decisionContextUsing(function () use (&$brand): array {
            return ['brand' => $brand];
        });
        ConfiguredPostControl::bind($this->ownInSql());
        $this->record($this->ada, 'update', $this->teamPost, Verdict::Granted);

        foreach ([1, '1', null] as $brand) {
            $this->assertFalse($this->ada->can('update', $this->teamPost));
        }
    }

    /** @return array<string, array{Closure(): mixed, string}> */
    public static function invalidContexts(): array
    {
        return [
            'a string' => [fn (): string => 'brand', 'returned a value of type [string]. Return an array of scalar values keyed by name'],
            'nothing' => [fn () => null, 'returned a value of type [null]'],
            'a list' => [fn (): array => [7], 'returned a value under the integer key [0]'],
            'a numeric key' => [fn (): array => ['7' => 'eu'], 'returned a value under the integer key [7]'],
            'a model' => [fn (): array => ['client' => new Client], sprintf('returned a value of type [%s] under the [client] key', Client::class)],
            'a float' => [fn (): array => ['ratio' => 0.5], 'returned a value of type [float] under the [ratio] key'],
            'an array' => [fn (): array => ['brands' => [1, 2]], 'returned a value of type [array] under the [brands] key'],
        ];
    }

    #[Test]
    #[DataProvider('invalidContexts')]
    public function it_refuses_a_resolver_that_returns_anything_but_named_scalar_values(Closure $resolver, string $message): void
    {
        Access::decisionContextUsing($resolver);
        ConfiguredPostControl::bind($this->ownInSql());

        $this->expectException(InvalidDecisionContextException::class);
        $this->expectExceptionMessage($message);

        $this->ada->can('update', $this->adaPost);
    }
}

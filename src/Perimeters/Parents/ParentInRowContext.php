<?php

namespace Lomkit\Access\Perimeters\Parents;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Decisions\MemoizedBranch;
use Lomkit\Access\Decisions\RowContextSwitch;
use Lomkit\Access\Decisions\RowContextValues;
use Lomkit\Access\Exceptions\RowContextSwitchException;
use Lomkit\Access\Search\Parents\ParentSearch;
use Lomkit\Access\Search\SearchKeyValues;

/** @internal */
final readonly class ParentInRowContext implements ParentDecision
{
    public function __construct(
        private ParentInRequestContext $request,
        private Delegation $delegation,
        private ParentSearch $search,
        private string $column,
        private object $perimeter,
    ) {}

    #[\Override]
    public function allows(AccessContext $context): bool
    {
        return $this->branches($context, fn (): bool => true) !== [];
    }

    /** @param  Builder<covariant Model>  $query */
    #[\Override]
    public function scope(Builder $query, AccessContext $context): void
    {
        $query->where(function (Builder $rows) use ($context): void {
            $branches = $this->branches($context, function (AccessContext $child, int|string $value) use ($rows): bool {
                $rows->orWhere(function (Builder $branch) use ($child, $value): void {
                    $branch->where($branch->qualifyColumn($this->column), $value);
                    $this->request->scope($branch, $child);
                });

                return true;
            });

            if ($branches === []) {
                $rows->whereRaw('0=1');
            }
        });
    }

    #[\Override]
    public function admitsDraft(Model $draft, AccessContext $context): bool
    {
        $stored = $draft->getAttributes()[$this->column] ?? null;

        if ($stored === null || $this->delegation->parentAbilityFor($context->ability()) === null) {
            return false;
        }

        $switch = RowContextSwitch::current();
        $values = $switch->values();
        $value = $values->match($stored);

        if ($value === null) {
            return false;
        }

        return $this->branch($switch, $values, $value, $context, fn (AccessContext $child): bool => $this->request->admitsDraft($draft, $child))[1] === [true];
    }

    #[\Override]
    public function fingerprint(AccessContext $context): string
    {
        return (new MemoizedBranch($this->perimeter))->fingerprint($context, fn (): string => implode('', $this->branches(
            $context,
            fn (AccessContext $child, int|string $value): string => '['.SearchKeyValues::component($value).':'.$this->request->fingerprint($child).']',
        )));
    }

    /** @return list<string> */
    #[\Override]
    public function readerKeys(AccessContext $context): array
    {
        $keys = [];

        foreach ($this->branches($context, fn (AccessContext $child, int|string $value): array => $this->search->readerKeys($child, $value)) as $branch) {
            array_push($keys, ...$branch);
        }

        return array_values(array_unique($keys));
    }

    /**
     * @template TResult
     *
     * @param  Closure(AccessContext, int|string): TResult  $decide
     * @return list<TResult>
     *
     * @throws RowContextSwitchException When two values are switched to the same decision context.
     */
    private function branches(AccessContext $context, Closure $decide): array
    {
        if ($this->delegation->parentAbilityFor($context->ability()) === null) {
            return [];
        }

        $switch = RowContextSwitch::current();
        $values = $switch->values();
        $contexts = [];
        $results = [];

        foreach ($values->all() as $value) {
            [$switched, $branch] = $this->branch($switch, $values, $value, $context, $decide);
            $seen = serialize($switched);

            if (array_key_exists($seen, $contexts)) {
                throw RowContextSwitchException::sameContext($contexts[$seen], $value, $switched);
            }

            $contexts[$seen] = $value;
            array_push($results, ...$branch);
        }

        return $results;
    }

    /**
     * @template TResult
     *
     * @param  Closure(AccessContext, int|string): TResult  $decide
     * @return array{array<string, string|int|bool|null>, list<TResult>}
     */
    private function branch(RowContextSwitch $switch, RowContextValues $values, int|string $value, AccessContext $context, Closure $decide): array
    {
        return $switch->within($values, $value, function () use ($context, $value, $decide): array {
            $child = AccessContext::of($context->reader(), $context->ability());
            $reached = (new MemoizedBranch($this->perimeter))->reached($child, $value, $this->request->allows(...));

            return [$child->decisionContext(), $reached ? [$decide($child, $value)] : []];
        });
    }
}

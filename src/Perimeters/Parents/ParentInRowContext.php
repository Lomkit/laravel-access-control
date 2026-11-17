<?php

namespace Lomkit\Access\Perimeters\Parents;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Decisions\MemoizedBranch;
use Lomkit\Access\Decisions\RowContextSwitch;
use Lomkit\Access\Decisions\RowContextValues;
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

        return $this->branch($switch, $values, $value, $context, fn (AccessContext $child): bool => $this->request->admitsDraft($draft, $child)) === [true];
    }

    #[\Override]
    public function fingerprint(AccessContext $context): string
    {
        return implode('', $this->branches(
            $context,
            fn (AccessContext $child, int|string $value): string => '['.SearchKeyValues::component($value).':'.$this->request->fingerprint($child).']',
        ));
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
     */
    private function branches(AccessContext $context, Closure $decide): array
    {
        if ($this->delegation->parentAbilityFor($context->ability()) === null) {
            return [];
        }

        $switch = RowContextSwitch::current();
        $values = $switch->values();
        $results = [];

        foreach ($values->all() as $value) {
            array_push($results, ...$this->branch($switch, $values, $value, $context, $decide));
        }

        return $results;
    }

    /**
     * @template TResult
     *
     * @param  Closure(AccessContext, int|string): TResult  $decide
     * @return list<TResult>
     */
    private function branch(RowContextSwitch $switch, RowContextValues $values, int|string $value, AccessContext $context, Closure $decide): array
    {
        return $switch->within($values, $value, function () use ($context, $value, $decide): array {
            $child = AccessContext::of($context->reader(), $context->ability());

            return (new MemoizedBranch($this->perimeter))->reached($child, $this->request->allows(...)) ? [$decide($child, $value)] : [];
        });
    }
}

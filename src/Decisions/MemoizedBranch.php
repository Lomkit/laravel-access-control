<?php

namespace Lomkit\Access\Decisions;

use Closure;
use Illuminate\Container\Container;
use Lomkit\Access\AccessContext;

/** @internal */
final readonly class MemoizedBranch
{
    public function __construct(private object $perimeter) {}

    /** @param  Closure(AccessContext): bool  $reaches */
    public function reached(AccessContext $context, Closure $reaches): bool
    {
        $key = BranchKey::of($this->perimeter, $context);

        if ($key === null) {
            return $reaches($context);
        }

        $memo = Container::getInstance()->make(DecisionMemo::class);
        $kept = $memo->branch($key)?->for($this->perimeter);

        if ($kept !== null) {
            return $kept;
        }

        $reached = $reaches($context);
        $memo->keepBranch($key, new KeptBranch($this->perimeter, $reached));

        return $reached;
    }
}

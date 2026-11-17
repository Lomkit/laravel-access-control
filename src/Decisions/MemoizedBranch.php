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
    public function reached(AccessContext $context, int|string $value, Closure $reaches): bool
    {
        $key = BranchKey::of($this->perimeter, $context, $value);
        $memo = $this->memo();
        $kept = $key === null ? null : $memo->branch($key)?->for($this->perimeter);

        if ($kept !== null) {
            return $kept;
        }

        $reached = $reaches($context);

        if ($key !== null) {
            $memo->keepBranch($key, new KeptBranch($this->perimeter, $reached));
        }

        return $reached;
    }

    /** @param  Closure(): string  $fingerprint */
    public function fingerprint(AccessContext $context, Closure $fingerprint): string
    {
        $key = BranchKey::of($this->perimeter, $context, null);
        $memo = $this->memo();
        $kept = $key === null ? null : $memo->fingerprint($key)?->for($this->perimeter);

        if ($kept !== null) {
            return $kept;
        }

        $computed = $fingerprint();

        if ($key !== null) {
            $memo->keepFingerprint($key, new KeptBranch($this->perimeter, $computed));
        }

        return $computed;
    }

    private function memo(): DecisionMemo
    {
        return Container::getInstance()->make(DecisionMemo::class);
    }
}

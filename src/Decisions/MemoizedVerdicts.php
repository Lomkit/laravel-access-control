<?php

namespace Lomkit\Access\Decisions;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;

/** @internal */
final readonly class MemoizedVerdicts implements KnownVerdicts
{
    public function __construct(private DecisionMemo $memo) {}

    #[\Override]
    public function recall(Model $row, SqlCheck $check, AccessContext $context): ?Verdict
    {
        $key = DecisionKey::of($context, $row::class, $row->getKey(), $check->fingerprint());

        return $key === null ? null : $this->memo->recall($key);
    }
}

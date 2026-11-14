<?php

namespace Lomkit\Access\Decisions;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;

/** @internal */
interface KnownVerdicts
{
    public function recall(Model $row, SqlCheck $check, AccessContext $context): ?Verdict;
}

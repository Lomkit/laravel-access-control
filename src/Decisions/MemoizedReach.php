<?php

namespace Lomkit\Access\Decisions;

use Lomkit\Access\AccessContext;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\ReachedPerimeters;

/** @internal */
final readonly class MemoizedReach
{
    public function __construct(private DecisionMemo $memo) {}

    public function reachedBy(Control $control, AccessContext $context): ReachedPerimeters
    {
        $key = ReachKey::of($control, $context);

        if ($key === null) {
            return $control->declaration()->reachedBy($context);
        }

        $kept = $this->memo->reach($key)?->for($control);

        if ($kept !== null) {
            return $kept;
        }

        $reached = $control->declaration()->reachedBy($context);
        $this->memo->keepReach($key, new KeptReach($control, $reached));

        return $reached;
    }
}

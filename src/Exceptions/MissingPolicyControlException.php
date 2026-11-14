<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class MissingPolicyControlException extends LogicException
{
    public static function for(string $policy, string $method): self
    {
        return new self(sprintf(
            'The [%s] policy cannot decide [%s] without a row, since it names no control. Declare the control of its model in the policy with protected string $control = PostControl::class, or delete the policy and call Access::registerPolicies().',
            $policy,
            $method,
        ));
    }
}

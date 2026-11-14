<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class PolicyControlMismatchException extends LogicException
{
    public static function for(string $policy, string $policyControl, string $model, string $modelControl): self
    {
        return new self(sprintf(
            'The [%s] policy names the [%s] control, but the [%s] model is controlled by [%s]. A policy decides with the control of its model: name [%s] in the policy.',
            $policy,
            $policyControl,
            $model,
            $modelControl,
            $modelControl,
        ));
    }
}

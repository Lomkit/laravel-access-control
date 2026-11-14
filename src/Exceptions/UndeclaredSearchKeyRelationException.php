<?php

namespace Lomkit\Access\Exceptions;

use Illuminate\Database\LazyLoadingViolationException;
use LogicException;

final class UndeclaredSearchKeyRelationException extends LogicException
{
    public static function for(string $name, LazyLoadingViolationException $violation): self
    {
        return new self(sprintf(
            'The row callback of the [%s] search keys reads the [%s] relation of the [%s] model, which its load does not declare. The keys are computed with lazy loading disabled: add the relation to load: [\'%s\'].',
            $name,
            $violation->relation,
            $violation->model,
            $violation->relation,
        ), previous: $violation);
    }
}

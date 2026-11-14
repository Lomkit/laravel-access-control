<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class UnquotableSearchKeyException extends LogicException
{
    public static function typesense(string $value): self
    {
        return new self(sprintf(
            'The search key [%s] holds a backtick, which a Typesense filter cannot quote, so no search token is signed with it. Search keys are made of [a-z0-9_.]: return the key from the searchKeys() of a perimeter or boundary.',
            $value,
        ));
    }
}

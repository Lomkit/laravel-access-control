<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class UndigestedSearchValueException extends LogicException
{
    public static function for(string $field, string $value): self
    {
        return new self(sprintf(
            'The [%s] filter received the value [%s], which is not the digest of a search key, so no search token is signed with it. A search filter sends each key as a digest: render the filter that Control::searchFilter() returns.',
            $field,
            $value,
        ));
    }
}

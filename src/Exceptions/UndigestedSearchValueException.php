<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class UndigestedSearchValueException extends LogicException
{
    private const string INVARIANT = 'The package writes every search key in a document, and renders it in a filter, as its digest only, so this value is a defect of the package.';

    public static function for(string $field, string $value): self
    {
        return new self(sprintf(
            'The [%s] filter received the value [%s], which is not the digest of a search key. %s',
            $field,
            $value,
            self::INVARIANT,
        ));
    }

    public static function inDocument(string $field, string $value): self
    {
        return new self(sprintf(
            'The [%s] field of a search document holds the value [%s], which is not the digest of a search key, so no filter would ever match it. %s',
            $field,
            $value,
            self::INVARIANT,
        ));
    }
}

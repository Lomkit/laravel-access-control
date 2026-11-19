<?php

namespace Lomkit\Access\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class UnknownSearchIndexException extends UnprocessableEntityHttpException
{
    public static function named(string $index): self
    {
        return new self(sprintf(
            'The search names the [%s] index, which is not the index of a ControlledSearchable model whose control is discovered. A guarded search reaches the indexes of those models only.',
            $index,
        ));
    }
}

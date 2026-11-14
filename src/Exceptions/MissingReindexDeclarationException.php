<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class MissingReindexDeclarationException extends LogicException
{
    public static function for(string $control, string $keys, string $watched): self
    {
        return new self(sprintf(
            'A reindex job was queued for the [%s] search keys of the [%s] control when [%s] changed, but the control no longer declares that reindexWhen(). Reindex the model with scout:import if its keys changed meanwhile.',
            $keys,
            $control,
            $watched,
        ));
    }
}

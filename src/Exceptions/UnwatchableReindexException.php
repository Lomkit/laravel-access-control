<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class UnwatchableReindexException extends LogicException
{
    public static function undiscovered(string $control): self
    {
        return new self(sprintf(
            'The [%s] control declares reindexWhen(), but it is not in the discovered directories, so a process that never builds it would not know which models to watch. Move it to app/Access/Controls, or add its directory with Access::discoverControlsIn(); if it is already there, run php artisan access:cache again, since the cached controls predate it.',
            $control,
        ));
    }

    public static function unsearchable(string $control, string $model): self
    {
        return new self(sprintf(
            'The [%s] control declares reindexWhen(), but its [%s] model does not use the ControlledSearchable trait, so it has no document to reindex. Use ControlledSearchable on the model, or remove reindexWhen().',
            $control,
            $model,
        ));
    }

    public static function staleCache(string $control): self
    {
        return new self(sprintf(
            'The [%s] control declares a reindexWhen() that the access cache does not know, so its watched models would not be reindexed. Run php artisan access:cache again.',
            $control,
        ));
    }
}

<?php

namespace Lomkit\Access\Controls;

use Lomkit\Access\Access;

trait HasControl
{
    /**
     * Boot the has control trait for a model.
     *
     * @return void
     */
    public static function bootHasControl()
    {
        static::addGlobalScope(new HasControlScope());
    }

    /**
     * Attempts to create a new control instance.
     *
     * @return Control|null The newly created control instance, or null if creation was unsuccessful.
     */
    protected static function newControl(): ?Control
    {
        return Access::controlForModel(static::class);
    }
}

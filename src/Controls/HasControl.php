<?php

namespace Lomkit\Access\Controls;

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
     * Retrieves a control instance for the model.
     *
     * @return Control The control instance for the model.
     */
    public static function control()
    {
        $control = static::newControl() ?? Control::controlForModel(static::class);

        return $control;
    }

    /**
     * Attempts to create a new control instance.
     *
     * @return Control|null The newly created control instance, or null if creation was unsuccessful.
     */
    protected static function newControl(): ?Control
    {
        return static::$control::new() ?? null;
    }
}

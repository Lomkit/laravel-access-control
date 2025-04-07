<?php

namespace Lomkit\Access\Controls;

use Illuminate\Database\Eloquent\Factories\Factory;

trait HasControl
{
    /**
     * Boot the HasControl trait.
     *
     * Registers a global query scope by adding an instance of HasControlScope to the model,
     * ensuring control-specific constraints are applied to all queries.
     *
     * @return void
     */
    public static function bootHasControl()
    {
        static::addGlobalScope(new HasControlScope());
    }

    /**
     * Retrieve a control instance associated with the model.
     *
     * This static method first attempts to create a new control instance using the newControl method.
     * If that call returns null, it falls back to obtaining the control instance for the model via
     * Control::controlForModel with the current model's class name.
     *
     * @return Control The control instance for the model.
     */
    public static function control()
    {
        $control = static::newControl() ?? Control::controlForModel(static::class);

        return $control;
    }

    /**
     * Creates a new control instance.
     *
     * This method attempts to instantiate a new control object by calling the static "new"
     * method on the control class defined in the static property. If the instantiation fails,
     * it returns null.
     *
     * @return Control|null A newly created control instance or null if the creation fails.
     */
    protected static function newControl(): ?Control
    {
        return static::$control::new() ?? null;
    }
}

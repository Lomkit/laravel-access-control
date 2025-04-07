<?php

namespace Lomkit\Access\Controls;

use Illuminate\Database\Eloquent\Factories\Factory;

trait HasControl
{
    /**
     * Boot the HasControl trait by attaching a global query scope to the model.
     *
     * This method automatically applies the HasControlScope to ensure that control-related
     * constraints are incorporated into all queries for models using this trait.
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
     * This method attempts to create a new control instance using the static `newControl()` method.
     * If a new instance is not available, it falls back to obtaining a control instance via `Control::controlForModel()`
     * to ensure that the model always has an associated control.
     *
     * @return Control A control instance associated with the model.
     */
    public static function control()
    {
        $control = static::newControl() ?? Control::controlForModel(static::class);

        return $control;
    }

    /**
     * Creates and returns a new control instance using the static control property's factory method.
     *
     * This method calls the static control factory's "new" method to instantiate a new control object.
     * If the instantiation fails, it returns null.
     *
     * @return Control|null The new control instance, or null if no instance could be created.
     */
    protected static function newControl(): ?Control
    {
        return static::$control::new() ?? null;
    }
}

<?php

namespace Lomkit\Access\Controls;

trait HasControl
{
    /**
     * Boot the HasControl trait for the model.
     *
     * Registers a global query scope using HasControlScope to apply control-related query constraints.
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
     * This method first attempts to obtain a new control instance via newControl().
     * If that returns null, it falls back to retrieving a control instance specific to the model class.
     *
     * @return Control A control instance corresponding to the model.
     */
    public static function control()
    {
        $control = static::newControl() ?? Control::controlForModel(static::class);

        return $control;
    }

    /**
     * Creates a new control instance for the model.
     *
     * This method attempts to instantiate a control by invoking the static new() method
     * on the control class specified by the static $control property. It returns null if no
     * instance can be created.
     *
     * @return Control|null A new control instance, or null if instantiation is unsuccessful.
     */
    protected static function newControl(): ?Control
    {
        return static::$control::new() ?? null;
    }
}

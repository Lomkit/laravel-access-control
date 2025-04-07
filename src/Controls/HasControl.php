<?php

namespace Lomkit\Access\Controls;

use Illuminate\Database\Eloquent\Factories\Factory;

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
     * This method first attempts to create a new control instance using the newControl() method.
     * If a new instance is not available, it falls back to obtaining a control instance associated
     * with the model class via Control::controlForModel().
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
     * This method uses the static control factory defined by the $control property by invoking its `new` method.
     * It returns the new control instance if successfully created, or null if instantiation fails.
     *
     * @return Control|null The newly created control instance, or null if creation was unsuccessful.
     */
    protected static function newControl(): ?Control
    {
        return static::$control::new() ?? null;
    }
}

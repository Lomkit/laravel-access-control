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
     * Get a new factory instance for the model.
     *
     * @return Control
     */
    public static function control()
    {
        $control = static::newControl() ?? Control::controlForModel(static::class);

        return $control;
    }

    /**
     * Return a new control instance.
     *
     * @return Control|null
     */
    protected static function newControl(): Control|null
    {
        return static::$control::new() ?? null;
    }
}

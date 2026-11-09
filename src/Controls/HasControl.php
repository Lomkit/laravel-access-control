<?php

namespace Lomkit\Access\Controls;

use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\MissingControlException;

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
     * Get the control registered for the model.
     *
     * @throws MissingControlException When no control is registered for the model.
     *
     * @return Control The control instance of the model.
     */
    public function newControl(): Control
    {
        return Access::controlForModel(static::class) ?? throw MissingControlException::forModel(static::class);
    }
}

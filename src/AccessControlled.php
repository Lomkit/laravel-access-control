<?php

namespace Lomkit\Access;

use Illuminate\Support\Facades\App;
use Lomkit\Access\Controls\Control;

trait AccessControlled
{
    /**
     * Return the control instance string
     *
     * @return class-string<Control>
     */
    public function getControl():string
    {
        return '';
    }

    /**
     * Return a new control instance
     *
     * @return Control
     */
    public function newControl():Control
    {
        return App::make($this->getControl());
    }

    /**
     * Boot the access controlled trait for a model.
     *
     * @return void
     */
    public static function bootAccessControlled()
    {
        static::addGlobalScope(new ControlScope);
    }
}
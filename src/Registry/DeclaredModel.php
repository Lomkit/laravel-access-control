<?php

namespace Lomkit\Access\Registry;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Exceptions\InvalidControlModelException;
use ReflectionProperty;

final class DeclaredModel
{
    /**
     * @param  class-string<Control>  $control
     * @return class-string<Model>
     *
     * @throws InvalidControlModelException When the control declares no Eloquent model.
     */
    public static function of(string $control): string
    {
        $property = new ReflectionProperty($control, 'model');

        if (! $property->hasDefaultValue()) {
            throw InvalidControlModelException::missing($control);
        }

        $model = $property->getDefaultValue();

        if (! is_string($model)) {
            throw InvalidControlModelException::notAClass($control, get_debug_type($model));
        }

        if (! class_exists($model)) {
            throw InvalidControlModelException::notAClass($control, $model);
        }

        if (! is_subclass_of($model, Model::class)) {
            throw InvalidControlModelException::notAModel($control, $model);
        }

        return $model;
    }
}

<?php

namespace Lomkit\Access\Registry;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Attributes\ControlledBy;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Exceptions\InvalidControlledByException;
use Lomkit\Access\Exceptions\InvalidControlModelException;
use ReflectionClass;

final class DeclaredControl
{
    /**
     * @param  class-string<Model>  $model
     * @return class-string<Control>|null
     *
     * @throws InvalidControlledByException When the attribute names no control of the model.
     * @throws InvalidControlModelException When the named control declares no Eloquent model.
     */
    public static function of(string $model): ?string
    {
        $attributes = (new ReflectionClass($model))->getAttributes(ControlledBy::class);

        if ($attributes === []) {
            return null;
        }

        $arguments = $attributes[0]->getArguments();
        $control = $arguments['control'] ?? $arguments[0] ?? null;

        if (! is_string($control) || ! ConcreteControl::is($control)) {
            throw InvalidControlledByException::notAControl($model, $control);
        }

        $controlledModel = DeclaredModel::of($control);

        if ($controlledModel !== $model) {
            throw InvalidControlledByException::controlsAnotherModel($model, $control, $controlledModel);
        }

        return $control;
    }
}

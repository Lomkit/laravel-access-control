<?php

namespace Lomkit\Access\Perimeters;

use Lomkit\Access\Exceptions\UnbuildablePerimeterException;
use ReflectionClass;

/** @internal */
final class PerimeterConstructor
{
    /**
     * @template TPerimeter of Perimeter
     *
     * @param  class-string<TPerimeter>  $perimeter
     * @return TPerimeter
     *
     * @throws UnbuildablePerimeterException If the constructor of the class requires arguments or is not public.
     */
    public static function build(string $perimeter): Perimeter
    {
        if (! self::builds($perimeter)) {
            throw UnbuildablePerimeterException::for($perimeter);
        }

        return (new ReflectionClass($perimeter))->newInstance();
    }

    /** @param  class-string<Perimeter>  $perimeter */
    public static function builds(string $perimeter): bool
    {
        $constructor = (new ReflectionClass($perimeter))->getConstructor();

        return $constructor === null || ($constructor->isPublic() && $constructor->getNumberOfRequiredParameters() === 0);
    }
}

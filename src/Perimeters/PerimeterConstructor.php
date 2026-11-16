<?php

namespace Lomkit\Access\Perimeters;

use Lomkit\Access\Exceptions\UnbuildablePerimeterException;
use ReflectionClass;
use ReflectionParameter;

/** @internal */
final class PerimeterConstructor
{
    /**
     * @template TPerimeter of Perimeter
     *
     * @param  class-string<TPerimeter>  $perimeter
     * @return TPerimeter
     *
     * @throws UnbuildablePerimeterException If the class is abstract, or its constructor requires arguments or is not public.
     */
    public static function build(string $perimeter): Perimeter
    {
        $refusal = self::refusal($perimeter);

        if ($refusal !== null) {
            throw $refusal;
        }

        return (new ReflectionClass($perimeter))->newInstance();
    }

    /** @param  class-string<Perimeter>  $perimeter */
    public static function builds(string $perimeter): bool
    {
        return self::refusal($perimeter) === null;
    }

    /** @param  class-string<Perimeter>  $perimeter */
    public static function refusal(string $perimeter): ?UnbuildablePerimeterException
    {
        $class = new ReflectionClass($perimeter);
        $constructor = $class->getConstructor();

        return match (true) {
            $class->isAbstract() => UnbuildablePerimeterException::abstract($perimeter),
            $constructor !== null && ! $constructor->isPublic() => UnbuildablePerimeterException::notPublic($perimeter),
            $constructor !== null && $constructor->getNumberOfRequiredParameters() > 0 => UnbuildablePerimeterException::requiresArguments(
                $perimeter,
                array_values(array_map(
                    fn (ReflectionParameter $parameter): string => $parameter->getName(),
                    array_filter($constructor->getParameters(), fn (ReflectionParameter $parameter): bool => ! $parameter->isOptional()),
                )),
            ),
            default => null,
        };
    }
}

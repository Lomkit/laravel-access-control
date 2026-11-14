<?php

namespace Lomkit\Access\Tests\Fixtures;

use Illuminate\Container\Container;
use Lomkit\Access\Access;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Registry\ControlRegistry;

trait ConfiguresPerimeters
{
    /** @var list<Perimeter|Levels> */
    private array $declaredPerimeters;

    private ?Boundary $declaredBoundary = null;

    final public function __construct(Perimeter|Levels ...$perimeters)
    {
        $this->declaredPerimeters = array_values($perimeters);
    }

    public static function bind(Perimeter|Levels ...$perimeters): static
    {
        $control = new static(...$perimeters);

        Container::getInstance()->instance(parent::class, $control);
        Container::getInstance()->forgetInstance(ControlRegistry::class);
        Access::clearResolvedInstance(ControlRegistry::class);

        return $control;
    }

    public static function bindWithin(Boundary $boundary, Perimeter|Levels ...$perimeters): static
    {
        $control = static::bind(...$perimeters);
        $control->declaredBoundary = $boundary;

        return $control;
    }

    #[\Override]
    protected function perimeters(): array
    {
        return $this->declaredPerimeters;
    }

    #[\Override]
    protected function boundary(): Boundary
    {
        return $this->declaredBoundary ?? parent::boundary();
    }
}

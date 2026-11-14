<?php

namespace Lomkit\Access\Tests\Fixtures;

use Illuminate\Container\Container;
use Lomkit\Access\Perimeters\Perimeter;

trait ConfiguresPerimeters
{
    /** @var list<Perimeter> */
    private array $declaredPerimeters;

    final public function __construct(Perimeter ...$perimeters)
    {
        $this->declaredPerimeters = array_values($perimeters);
    }

    public static function bind(Perimeter ...$perimeters): static
    {
        $control = new static(...$perimeters);

        Container::getInstance()->instance(parent::class, $control);

        return $control;
    }

    #[\Override]
    protected function perimeters(): array
    {
        return $this->declaredPerimeters;
    }
}

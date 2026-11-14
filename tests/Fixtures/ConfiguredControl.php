<?php

namespace Lomkit\Access\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Access;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;

class ConfiguredControl extends Control
{
    /** @var list<Perimeter> */
    private array $declaredPerimeters;

    /** @param  class-string<Model>  $model */
    public function __construct(string $model, Perimeter ...$perimeters)
    {
        $this->model = $model;
        $this->declaredPerimeters = array_values($perimeters);
    }

    /** @param  class-string<Model>  $model */
    public static function register(string $model, Perimeter ...$perimeters): self
    {
        Access::flush();

        $control = new self($model, ...$perimeters);

        (new Access)->addControl($control);

        return $control;
    }

    #[\Override]
    protected function perimeters(): array
    {
        return $this->declaredPerimeters;
    }
}

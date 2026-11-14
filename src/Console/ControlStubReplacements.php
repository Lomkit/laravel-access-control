<?php

namespace Lomkit\Access\Console;

final readonly class ControlStubReplacements
{
    /** @param  list<string>  $perimeters */
    public function __construct(
        private string $model,
        private array $perimeters,
    ) {}

    /** @return array<string, string> */
    public function toArray(): array
    {
        $perimeters = implode("\n            ", array_map(
            fn (string $perimeter): string => (new GeneratedPerimeter($perimeter))->code(),
            $this->perimeters,
        ));

        return [
            '{{ perimeters }}' => $perimeters,
            '{{perimeters}}' => $perimeters,
            'DummyFullModelClass' => $this->model,
            '{{ namespacedModel }}' => $this->model,
            '{{namespacedModel}}' => $this->model,
            'DummyModelClass' => class_basename($this->model),
            '{{ model }}' => class_basename($this->model),
            '{{model}}' => class_basename($this->model),
            'DummyModelVariable' => lcfirst(class_basename($this->model)),
            '{{ modelVariable }}' => lcfirst(class_basename($this->model)),
            '{{modelVariable}}' => lcfirst(class_basename($this->model)),
        ];
    }
}

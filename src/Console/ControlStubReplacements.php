<?php

namespace Lomkit\Access\Console;

/** @internal */
final readonly class ControlStubReplacements
{
    /**
     * @param  list<string>  $perimeters
     * @param  list<GeneratedParent>  $parents
     */
    public function __construct(
        private string $model,
        private array $perimeters,
        private array $parents = [],
    ) {}

    /** @return array<string, string> */
    public function toArray(): array
    {
        $perimeters = implode("\n            ", [
            ...array_map(fn (GeneratedParent $parent): string => $parent->code(), $this->parents),
            ...array_map(fn (string $perimeter): string => (new GeneratedPerimeter($perimeter))->code(), $this->perimeters),
        ]);

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

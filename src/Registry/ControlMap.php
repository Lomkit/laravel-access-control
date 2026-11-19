<?php

namespace Lomkit\Access\Registry;

use Lomkit\Access\Exceptions\DuplicateControlException;

/** @internal */
final readonly class ControlMap
{
    /** @param  array<string, string>  $controls */
    public function __construct(private array $controls = []) {}

    /** @throws DuplicateControlException When another control is already mapped to the model. */
    public function with(string $model, string $control): self
    {
        $mapped = $this->controls[$model] ?? $control;

        if ($mapped !== $control) {
            throw DuplicateControlException::forModel($model, $mapped, $control);
        }

        return new self([...$this->controls, $model => $control]);
    }

    public function find(string $model): ?string
    {
        return $this->controls[$model] ?? null;
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return $this->controls;
    }
}

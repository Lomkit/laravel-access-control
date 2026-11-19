<?php

namespace Lomkit\Access\Console;

use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Perimeters\PerimeterConstructor;
use Lomkit\Access\Perimeters\PerimeterHook;

/** @internal */
final readonly class GeneratedPerimeter
{
    public function __construct(private string $class) {}

    public function isBuildable(): bool
    {
        return ! is_subclass_of($this->class, Perimeter::class) || PerimeterConstructor::builds($this->class);
    }

    public function skippedWarning(): ?string
    {
        if (! is_subclass_of($this->class, Perimeter::class)) {
            return null;
        }

        $refusal = PerimeterConstructor::refusal($this->class);

        return $refusal === null ? null : $refusal->getMessage().' The control does not list it: add it to perimeters() by hand.';
    }

    public function code(): string
    {
        $code = "\\{$this->class}::new()";

        foreach (PerimeterHook::cases() as $hook) {
            if (! $this->overrides($hook)) {
                $code .= "\n                ".$this->callback($hook);
            }
        }

        return "{$code},";
    }

    private function overrides(PerimeterHook $hook): bool
    {
        return is_subclass_of($this->class, Perimeter::class) && $hook->isOverriddenIn($this->class);
    }

    private function callback(PerimeterHook $hook): string
    {
        return match ($hook) {
            PerimeterHook::Allows => <<<'CALLBACK'
                ->allowed(function (Model $user, Ability $ability): bool {
                                    return false;
                                })
                CALLBACK,
            PerimeterHook::Includes => <<<'CALLBACK'
                ->should(function (Model $user, Model $model): bool {
                                    return false;
                                })
                CALLBACK,
            PerimeterHook::Scope => <<<'CALLBACK'
                ->query(function (Builder $query, Model $user): void {
                                    $query->whereRaw('0=1');
                                })
                CALLBACK,
        };
    }
}

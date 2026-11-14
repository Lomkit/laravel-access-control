<?php

namespace Lomkit\Access\Console;

use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Perimeters\PerimeterHook;

final readonly class GeneratedPerimeter
{
    public function __construct(private string $class) {}

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
            PerimeterHook::ScoutScope => <<<'CALLBACK'
                ->scoutQuery(function (\Laravel\Scout\Builder $query, Model $user): void {
                                    $query->where('__NOT_A_VALID_FIELD__', 0);
                                })
                CALLBACK,
        };
    }
}

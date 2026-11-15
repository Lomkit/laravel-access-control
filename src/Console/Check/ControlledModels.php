<?php

namespace Lomkit\Access\Console\Check;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;

/** @internal */
final readonly class ControlledModels
{
    /** @param  array<class-string<Model>, class-string<Control>>  $controls */
    public function __construct(private array $controls) {}

    /** @return array<class-string<Model>, class-string<Control>> */
    public function all(): array
    {
        return $this->controls;
    }

    /** @param  list<string>  $targets */
    public function only(array $targets): self
    {
        if ($targets === []) {
            return $this;
        }

        $names = array_map(fn (string $target): string => ltrim($target, '\\'), $targets);

        return new self(array_filter(
            $this->controls,
            fn (string $control, string $model): bool => in_array($model, $names, true) || in_array($control, $names, true),
            ARRAY_FILTER_USE_BOTH,
        ));
    }
}

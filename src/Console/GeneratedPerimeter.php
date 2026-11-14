<?php

namespace Lomkit\Access\Console;

final readonly class GeneratedPerimeter
{
    public function __construct(private string $class) {}

    public function code(): string
    {
        return <<<PERIMETER
        {$this->class}::new()
                        ->allowed(function (Model \$user, string \$method): bool {
                            return false;
                        })
                        ->should(function (Model \$user, Model \$model): bool {
                            return false;
                        })
                        ->query(function (Builder \$query, Model \$user): void {
                            \$query->whereRaw('0=1');
                        })
                        ->scoutQuery(function (\Laravel\Scout\Builder \$query, Model \$user): void {
                            \$query->where('__NOT_A_VALID_FIELD__', 0);
                        }),
        PERIMETER;
    }
}

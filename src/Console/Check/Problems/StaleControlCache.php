<?php

namespace Lomkit\Access\Console\Check\Problems;

/** @internal */
final readonly class StaleControlCache implements Problem
{
    public function __construct(private string $path) {}

    #[\Override]
    public function kind(): ProblemKind
    {
        return ProblemKind::StaleControlCache;
    }

    #[\Override]
    public function subject(): string
    {
        return $this->path;
    }

    #[\Override]
    public function message(): string
    {
        return sprintf('The [%s] cache maps the models to other controls than the ones discovered now, so the application uses the cached ones. Run "php artisan access:cache" again, or "php artisan access:clear".', $this->path);
    }
}

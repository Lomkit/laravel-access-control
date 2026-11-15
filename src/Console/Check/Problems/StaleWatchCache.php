<?php

namespace Lomkit\Access\Console\Check\Problems;

/** @internal */
final readonly class StaleWatchCache implements Problem
{
    public function __construct(
        private string $path,
        private bool $missing,
    ) {}

    #[\Override]
    public function kind(): ProblemKind
    {
        return ProblemKind::StaleWatchCache;
    }

    #[\Override]
    public function subject(): string
    {
        return $this->path;
    }

    #[\Override]
    public function message(): string
    {
        return $this->missing
            ? sprintf('The controls are cached, but the [%s] cache of the watched models is missing or unreadable, so no reindexWhen() rule reindexes a document when the model it watches changes. Run "php artisan access:cache" again.', $this->path)
            : sprintf('The [%s] cache lists other watched models than the reindexWhen() rules of the discovered controls, so the documents whose rule it misses keep stale access keys. Run "php artisan access:cache" again.', $this->path);
    }
}

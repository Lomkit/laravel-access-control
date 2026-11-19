<?php

namespace Lomkit\Access\Search;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Boundaries\NoBoundary;

/** @internal */
final readonly class BoundarySearch
{
    private function __construct(
        private string $boundary,
        private bool $bounded,
        private ?SearchKeys $keys,
    ) {}

    public static function of(Boundary $boundary): self
    {
        return new self($boundary::class, ! $boundary instanceof NoBoundary, $boundary->applySearchKeys());
    }

    public function isBounded(): bool
    {
        return $this->bounded;
    }

    public function isSearchable(): bool
    {
        return ! $this->bounded || $this->keys !== null;
    }

    public function boundary(): string
    {
        return $this->boundary;
    }

    public function keys(): ?SearchKeys
    {
        return $this->keys;
    }

    /** @return list<string> */
    public function load(): array
    {
        return $this->keys?->load() ?? [];
    }

    /** @return list<string> */
    public function rowKeys(Model $row): array
    {
        return $this->keys?->rowKeys($row) ?? [];
    }

    /** @return list<string>|null */
    public function readerKeys(AccessContext $context): ?array
    {
        if (! $this->bounded) {
            return null;
        }

        return $this->keys?->readerKeys($context) ?? [];
    }
}

<?php

namespace Lomkit\Access\Search;

final readonly class SearchFilter
{
    /** @var array<string, list<string>> */
    private array $whereIns;

    /**
     * @param  list<string>|null  $boundary
     * @param  list<string>|null  $access
     */
    private function __construct(
        private bool $closed,
        private ?array $boundary,
        private ?array $access,
    ) {
        $whereIns = [];

        if ($boundary !== null) {
            $whereIns[SearchField::Boundary->value] = $boundary;
        }

        if ($access !== null) {
            $whereIns[SearchField::Access->value] = $access;
        }

        $this->whereIns = SearchKeyDigest::document($whereIns);
    }

    public static function closed(): self
    {
        return new self(true, [], []);
    }

    /**
     * @param  list<string>|null  $boundary
     * @param  list<string>|null  $access
     */
    public static function of(?array $boundary, ?array $access): self
    {
        if ($boundary === [] || $access === []) {
            return self::closed();
        }

        return new self(false, $boundary, $access);
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    /** @return list<string>|null */
    public function boundaryKeys(): ?array
    {
        return $this->boundary;
    }

    /** @return list<string>|null */
    public function accessKeys(): ?array
    {
        return $this->access;
    }

    /** @return array<string, list<string>> */
    public function whereIns(): array
    {
        return $this->whereIns;
    }

    /** @param  array<string, list<string>>  $document */
    public function matches(array $document): bool
    {
        if ($this->closed) {
            return false;
        }

        foreach ($this->whereIns as $field => $values) {
            if (array_intersect($document[$field] ?? [], $values) === []) {
                return false;
            }
        }

        return true;
    }
}

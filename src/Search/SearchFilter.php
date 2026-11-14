<?php

namespace Lomkit\Access\Search;

final readonly class SearchFilter
{
    /**
     * @param  list<string>|null  $boundary
     * @param  list<string>|null  $access
     */
    private function __construct(
        private bool $closed,
        private ?array $boundary,
        private ?array $access,
    ) {}

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

    /** @return array<string, list<string>> */
    public function whereIns(): array
    {
        $whereIns = [];

        if ($this->boundary !== null) {
            $whereIns[SearchField::Boundary->value] = $this->boundary;
        }

        if ($this->access !== null) {
            $whereIns[SearchField::Access->value] = $this->access;
        }

        return $whereIns;
    }

    /** @param  array<string, list<string>>  $document */
    public function matches(array $document): bool
    {
        if ($this->closed) {
            return false;
        }

        foreach ($this->whereIns() as $field => $values) {
            if (array_intersect($document[$field] ?? [], $values) === []) {
                return false;
            }
        }

        return true;
    }
}

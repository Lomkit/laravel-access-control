<?php

namespace Lomkit\Access\Search;

use Lomkit\Access\Exceptions\UndigestedSearchValueException;

/** @internal */
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
    public function readableBoundaryKeys(): ?array
    {
        return $this->boundary;
    }

    /** @return list<string>|null */
    public function readableAccessKeys(): ?array
    {
        return $this->access;
    }

    /** @return array<string, list<string>> */
    public function whereIns(): array
    {
        return $this->whereIns;
    }

    /**
     * @param  array<string, list<string>>  $document
     *
     * @throws UndigestedSearchValueException When a value of the document is not the digest of a search key.
     */
    public function matches(array $document): bool
    {
        self::checkDigested($document);

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

    /**
     * @param  array<string, list<string>>  $document
     *
     * @throws UndigestedSearchValueException When a value of the document is not the digest of a search key.
     */
    private static function checkDigested(array $document): void
    {
        foreach ($document as $field => $values) {
            foreach ($values as $value) {
                if (! SearchKeyDigest::isDigest($value)) {
                    throw UndigestedSearchValueException::inDocument($field, $value);
                }
            }
        }
    }
}

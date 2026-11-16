<?php

namespace Lomkit\Access\Decisions;

/**
 * @internal
 *
 * @template TValue
 */
final class CappedTable
{
    /** @var array<string, TValue> */
    private array $entries = [];

    public function __construct(private readonly int $cap) {}

    /** @param  TValue  $value */
    public function put(string $key, mixed $value): void
    {
        unset($this->entries[$key]);

        if (count($this->entries) >= $this->cap) {
            unset($this->entries[array_key_first($this->entries)]);
        }

        $this->entries[$key] = $value;
    }

    /** @return TValue|null */
    public function get(string $key): mixed
    {
        return $this->entries[$key] ?? null;
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function clear(): void
    {
        $this->entries = [];
    }
}

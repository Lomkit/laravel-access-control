<?php

namespace Lomkit\Access\Decisions;

/** @internal */
final class DecisionMemo
{
    public const int MAX_ENTRIES = 10_000;

    /** @var array<string, Verdict> */
    private array $verdicts = [];

    public function remember(DecisionKey $key, Verdict $verdict): void
    {
        unset($this->verdicts[$key->id()]);

        if (count($this->verdicts) >= self::MAX_ENTRIES) {
            unset($this->verdicts[array_key_first($this->verdicts)]);
        }

        $this->verdicts[$key->id()] = $verdict;
    }

    public function recall(DecisionKey $key): ?Verdict
    {
        return $this->verdicts[$key->id()] ?? null;
    }

    public function count(): int
    {
        return count($this->verdicts);
    }

    public function forget(): void
    {
        $this->verdicts = [];
    }
}

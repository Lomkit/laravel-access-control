<?php

namespace Lomkit\Access\Decisions;

/** @internal */
final class DecisionMemo
{
    public const int MAX_ENTRIES = 10_000;

    /** @var CappedTable<Verdict> */
    private CappedTable $verdicts;

    /** @var CappedTable<KeptReach> */
    private CappedTable $reaches;

    public function __construct()
    {
        $this->verdicts = new CappedTable(self::MAX_ENTRIES);
        $this->reaches = new CappedTable(self::MAX_ENTRIES);
    }

    public function remember(DecisionKey $key, Verdict $verdict): void
    {
        $this->verdicts->put($key->id(), $verdict);
    }

    public function recall(DecisionKey $key): ?Verdict
    {
        return $this->verdicts->get($key->id());
    }

    public function keepReach(ReachKey $key, KeptReach $reach): void
    {
        $this->reaches->put($key->id(), $reach);
    }

    public function reach(ReachKey $key): ?KeptReach
    {
        return $this->reaches->get($key->id());
    }

    public function count(): int
    {
        return $this->verdicts->count();
    }

    public function reachCount(): int
    {
        return $this->reaches->count();
    }

    public function forget(): void
    {
        $this->verdicts->clear();
        $this->reaches->clear();
    }
}

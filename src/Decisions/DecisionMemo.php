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

    /** @var CappedTable<KeptBranch> */
    private CappedTable $branches;

    public function __construct()
    {
        $this->verdicts = new CappedTable(self::MAX_ENTRIES);
        $this->reaches = new CappedTable(self::MAX_ENTRIES);
        $this->branches = new CappedTable(self::MAX_ENTRIES);
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

    public function keepBranch(BranchKey $key, KeptBranch $branch): void
    {
        $this->branches->put($key->id(), $branch);
    }

    public function branch(BranchKey $key): ?KeptBranch
    {
        return $this->branches->get($key->id());
    }

    public function count(): int
    {
        return $this->verdicts->count();
    }

    public function reachCount(): int
    {
        return $this->reaches->count();
    }

    public function isEmpty(): bool
    {
        return $this->verdicts->count() === 0 && $this->reaches->count() === 0 && $this->branches->count() === 0;
    }

    public function forget(): void
    {
        $this->verdicts->clear();
        $this->reaches->clear();
        $this->branches->clear();
    }
}

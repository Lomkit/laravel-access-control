<?php

namespace Lomkit\Access\Decisions;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;

/** @internal */
final readonly class BatchedVerdicts implements KnownVerdicts
{
    /** @param  array<class-string<Model>, array<string, Verdict>>  $verdicts */
    public function __construct(
        private string $fingerprint,
        private array $verdicts,
    ) {}

    #[\Override]
    public function recall(Model $row, SqlCheck $check, AccessContext $context): ?Verdict
    {
        $key = $row->getKey();

        if ($check->fingerprint() !== $this->fingerprint || (! is_int($key) && ! is_string($key))) {
            return null;
        }

        return $this->verdicts[$row::class][(string) $key] ?? null;
    }
}

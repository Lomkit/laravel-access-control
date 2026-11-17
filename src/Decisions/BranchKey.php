<?php

namespace Lomkit\Access\Decisions;

use Lomkit\Access\AccessContext;

/** @internal */
final readonly class BranchKey
{
    private function __construct(private string $id) {}

    public static function of(object $perimeter, AccessContext $context, int|string|null $value): ?self
    {
        $reader = $context->reader();
        $readerKey = DecisionKey::readerKey($reader);

        if ($readerKey === null) {
            return null;
        }

        return new self(serialize([
            $reader->getMorphClass(),
            $reader->getConnectionName(),
            $readerKey,
            $context->decisionContext(),
            $perimeter::class,
            spl_object_id($perimeter),
            $context->ability()->name(),
            $value === null ? null : (string) $value,
        ]));
    }

    public function id(): string
    {
        return $this->id;
    }
}

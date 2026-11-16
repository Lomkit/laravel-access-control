<?php

namespace Lomkit\Access\Decisions;

use Lomkit\Access\AccessContext;
use Lomkit\Access\Controls\Control;

/** @internal */
final readonly class ReachKey
{
    private function __construct(private string $id) {}

    public static function of(Control $control, AccessContext $context): ?self
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
            $control::class,
            spl_object_id($control),
            $context->ability()->name(),
        ]));
    }

    public function id(): string
    {
        return $this->id;
    }
}

<?php

namespace Lomkit\Access\Decisions;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;

/** @internal */
final readonly class DecisionKey
{
    private function __construct(private string $id) {}

    /** @param  class-string<Model>  $model */
    public static function of(AccessContext $context, string $model, mixed $row, string $check): ?self
    {
        $reader = $context->reader();
        $readerKey = $reader->getKey();

        if (! self::remembers($reader) || ! self::isKey($readerKey) || ! self::isKey($row)) {
            return null;
        }

        return new self(serialize([
            $reader->getMorphClass(),
            (string) $readerKey,
            $context->decisionContext(),
            $model,
            (string) $row,
            $context->ability()->name(),
            $check,
        ]));
    }

    public static function remembers(Model $reader): bool
    {
        return $reader->exists && self::isKey($reader->getKey());
    }

    public function id(): string
    {
        return $this->id;
    }

    /** @phpstan-assert-if-true int|string $key */
    private static function isKey(mixed $key): bool
    {
        return is_int($key) || (is_string($key) && $key !== '');
    }
}

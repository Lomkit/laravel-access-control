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
        $readerKey = self::readerKey($reader);

        if ($readerKey === null || ! self::isKey($row)) {
            return null;
        }

        return new self(serialize([
            $reader->getMorphClass(),
            $readerKey,
            $context->decisionContext(),
            $model,
            (string) $row,
            $context->ability()->name(),
            $check,
        ]));
    }

    public static function remembers(Model $reader): bool
    {
        return self::readerKey($reader) !== null;
    }

    public static function readerKey(Model $reader): ?string
    {
        $key = $reader->getKey();

        return $reader->exists && self::isKey($key) ? (string) $key : null;
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

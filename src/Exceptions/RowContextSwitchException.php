<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Throwable;

final class RowContextSwitchException extends LogicException
{
    public static function notCalled(int|string $value): self
    {
        return new self(sprintf(
            'The within closure given to Access::rowContextUsing() returned without calling the decide callback for the value [%s]. Switch the application to that value, call the callback once, and restore the previous state in a finally block.',
            $value,
        ));
    }

    public static function calledTwice(int|string $value): self
    {
        return new self(sprintf(
            'The within closure given to Access::rowContextUsing() called the decide callback more than once for the value [%s]. Call it exactly once, while the application acts in that value only.',
            $value,
        ));
    }

    /** @param  array<string, string|int|bool|null>  $context */
    public static function withoutEffect(int|string $value, array $context): self
    {
        return new self(sprintf(
            'The within closure given to Access::rowContextUsing() called the decide callback for the value [%s] while the decision context was still %s, the context of several values it was called from. Switch the state that Access::decisionContextUsing() reads before calling the callback, so that the parent is decided, and its decisions are kept, in that value alone.',
            $value,
            self::encode($context),
        ));
    }

    /** @param  list<int|string>  $seen */
    public static function otherValues(int|string $value, array $seen): self
    {
        return new self(sprintf(
            'The within closure given to Access::rowContextUsing() called the decide callback for the value [%s] while the values closure returned [%s]. Switch the application to the value the closure receives, so that the values closure returns that value alone while the callback runs.',
            $value,
            implode(', ', $seen),
        ));
    }

    /** @param  array<string, string|int|bool|null>  $context */
    public static function sameContext(int|string $first, int|string $second, array $context): self
    {
        return new self(sprintf(
            'The within closure given to Access::rowContextUsing() switched the values [%s] and [%s] to the same decision context %s. Make Access::decisionContextUsing() read the state that within switches, so that each value gets its own context and the decisions kept for one value are never reused for another.',
            $first,
            $second,
            self::encode($context),
        ));
    }

    /**
     * @param  array<string, string|int|bool|null>  $before
     * @param  array<string, string|int|bool|null>  $after
     */
    public static function leaked(int|string $value, array $before, array $after, ?Throwable $previous = null): self
    {
        return new self(sprintf(
            'The within closure given to Access::rowContextUsing() left the decision context on %s after deciding for the value [%s]%s, instead of restoring %s. Restore the previous state in a finally block: the next decisions of the request, or of the next request served by the same worker, would run in the wrong context.',
            self::encode($after),
            $value,
            $previous === null ? '' : ' and throwing '.$previous::class,
            self::encode($before),
        ), previous: $previous);
    }

    /** @param  array<string, string|int|bool|null>  $context */
    private static function encode(array $context): string
    {
        return json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

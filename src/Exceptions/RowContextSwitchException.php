<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

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
            'The within closure given to Access::rowContextUsing() called the decide callback for the value [%s] while the decision context was still %s, the context of the whole request. Switch the state that Access::decisionContextUsing() reads before calling the callback, so that the parent is decided, and its decisions are kept, in that value alone.',
            $value,
            self::encode($context),
        ));
    }

    /**
     * @param  array<string, string|int|bool|null>  $before
     * @param  array<string, string|int|bool|null>  $after
     */
    public static function leaked(int|string $value, array $before, array $after): self
    {
        return new self(sprintf(
            'The within closure given to Access::rowContextUsing() left the decision context on %s after deciding for the value [%s], instead of restoring %s. Restore the previous state in a finally block: the next decisions of the request, or of the next request served by the same worker, would run in the wrong context.',
            self::encode($after),
            $value,
            self::encode($before),
        ));
    }

    /** @param  array<string, string|int|bool|null>  $context */
    private static function encode(array $context): string
    {
        return json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

<?php

namespace Lomkit\Access\Exceptions;

use DateTimeInterface;
use LogicException;

final class PastSearchTokenExpiryException extends LogicException
{
    public static function for(string $model, DateTimeInterface $expiresAt): self
    {
        return new self(sprintf(
            'The search token for the [%s] model would expire at [%s], which is not in the future. Pass an expiry a few minutes to an hour ahead, such as now()->addMinutes(15): a token keeps the access of its reader until it expires.',
            $model,
            $expiresAt->format(DATE_ATOM),
        ));
    }
}

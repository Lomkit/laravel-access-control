<?php

namespace Lomkit\Access\Integrations\RestApi;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\InvalidAbilityException;
use Lomkit\Access\Exceptions\InvalidRestReaderException;
use Lomkit\Rest\Authorization\PendingChecks;
use Lomkit\Rest\Extensions\Extension;

/** @internal */
final readonly class RowCheckPreparation
{
    /** @param  class-string<Extension>  $extension */
    public function __construct(private string $extension) {}

    /**
     * @throws InvalidRestReaderException When the user is not an Eloquent model.
     * @throws InvalidAbilityException When a control cannot map the ability.
     */
    public function prepare(PendingChecks $checks): void
    {
        $reader = RestReader::of($checks->user, $this->extension);

        if ($reader === null) {
            return;
        }

        $controlled = $checks->rows->filter(fn (Model $row): bool => Access::hasControl($row));

        if ($controlled->isNotEmpty()) {
            Access::preload($reader, $checks->ability, $controlled);
        }
    }
}

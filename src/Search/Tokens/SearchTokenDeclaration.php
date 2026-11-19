<?php

namespace Lomkit\Access\Search\Tokens;

use Illuminate\Container\Attributes\Singleton;
use Lomkit\Access\Exceptions\ConflictingSearchTokensException;
use Lomkit\Access\Exceptions\ProxiedSearchTokenException;
use Lomkit\Access\Exceptions\UndeclaredSearchTokensException;

/** @internal */
#[Singleton]
final class SearchTokenDeclaration
{
    private ?bool $direct = null;

    /** @throws ConflictingSearchTokensException When the application already declared the other way. */
    public function declare(bool $direct): void
    {
        if ($this->direct !== null && $this->direct !== $direct) {
            throw ConflictingSearchTokensException::declared($this->direct, $direct);
        }

        $this->direct = $direct;
    }

    /**
     * @throws UndeclaredSearchTokensException When the application did not declare how browsers reach the engine.
     * @throws ProxiedSearchTokenException When browsers go through the route of the application.
     */
    public function ensureDirect(string $model): void
    {
        if ($this->direct === null) {
            throw UndeclaredSearchTokensException::forModel($model);
        }

        if (! $this->direct) {
            throw ProxiedSearchTokenException::forModel($model);
        }
    }
}

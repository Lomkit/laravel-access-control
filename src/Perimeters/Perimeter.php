<?php

namespace Lomkit\Access\Perimeters;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class Perimeter
{
    // @TODO: what for shared example ? (final on former project)

    protected Closure $queryCallback;

    public function should(Authenticatable $user, string $method, Model $model): bool
    {
        return false;
    }

    public function query(Closure $queryCallback): self
    {
        // @TODO: ok mais pas possible de le déclarer globalement alors, seems ok for me
        $this->queryCallback = $queryCallback;
        return $this;
    }

    /**
     * Get a new control instance for the given attributes.
     *
     * @return static
     */
    public static function new()
    {
        return (new static);
    }
}
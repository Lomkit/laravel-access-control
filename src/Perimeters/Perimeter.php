<?php

namespace Lomkit\Access\Perimeters;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class Perimeter
{
    // @TODO: what for shared example ? (final on former project)

    protected Closure $queryCallback;
    protected Closure $shouldCallback;

    public function applies(Model $user): bool
    {
        return false;
    }

    public function getShouldResult(Model $user, string $method, Model $model): bool
    {
        return ($this->shouldCallback)($user, $method, $model);
    }

    public function should(Closure $shouldCallback): self
    {
        $this->shouldCallback = $shouldCallback;
        return $this;
    }

    public function query(Closure $queryCallback): self
    {
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
        return new static();
    }
}

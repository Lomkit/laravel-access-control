<?php

namespace Lomkit\Access\Perimeters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Perimeter
{
    // @TODO: what for shared example ? (final on former project)

    protected Closure $queryCallback;
    protected Closure $shouldCallback;

    public function applies(Model $user): bool
    {
        return true;
    }

    public function applyShouldCallback(Model $user, string $method, Model $model): bool
    {
        return ($this->shouldCallback)($user, $method, $model);
    }

    public function applyQueryCallback(Builder $query, Model $user): Builder {
        return ($this->queryCallback)($query, $user);
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

    /**
     * Indicates if the Perimeter can overlay with others
     *
     * @return bool
     */
    protected function overlays(): bool
    {
        return false;
    }
}

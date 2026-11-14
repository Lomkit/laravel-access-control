<?php

namespace Lomkit\Access\Decisions;

use Illuminate\Database\Eloquent\Model;

final readonly class Draft
{
    private function __construct(private Model $model) {}

    public static function of(Model $model): self
    {
        return new self($model);
    }

    public function model(): Model
    {
        return $this->model;
    }
}

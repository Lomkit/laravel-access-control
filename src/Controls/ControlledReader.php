<?php

namespace Lomkit\Access\Controls;

use Illuminate\Database\Eloquent\Model;

/** @internal */
final readonly class ControlledReader
{
    public function __construct(private ?Model $reader) {}

    public function reader(): ?Model
    {
        return $this->reader;
    }
}

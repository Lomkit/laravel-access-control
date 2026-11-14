<?php

namespace Lomkit\Access;

use Illuminate\Database\Eloquent\Model;

final readonly class AccessContext
{
    private function __construct(
        private Model $reader,
        private Ability $ability,
    ) {}

    /** @internal */
    public static function of(Model $reader, Ability $ability): self
    {
        return new self($reader, $ability);
    }

    public function reader(): Model
    {
        return $this->reader;
    }

    public function ability(): Ability
    {
        return $this->ability;
    }
}

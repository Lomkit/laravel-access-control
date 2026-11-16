<?php

namespace Lomkit\Access\Tests\Fixtures\Types;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class RowsOfAnotherModelException extends LogicException
{
    /** @param  class-string<Model>  $expected */
    public static function for(string $expected, Model $actual): self
    {
        return new self(sprintf('The query reads [%s] rows, not [%s] rows.', $actual::class, $expected));
    }
}

<?php

namespace Lomkit\Access\Console\Check\Inventory;

/** @internal */
enum CheckMode: string
{
    case Memory = 'memory';
    case Sql = 'sql';

    public static function of(bool $inMemory): self
    {
        return $inMemory ? self::Memory : self::Sql;
    }
}

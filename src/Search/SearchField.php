<?php

namespace Lomkit\Access\Search;

enum SearchField: string
{
    case Access = '_access';
    case Boundary = '_boundary';

    /** @return list<string> */
    public static function names(): array
    {
        return array_map(fn (self $field): string => $field->value, self::cases());
    }
}

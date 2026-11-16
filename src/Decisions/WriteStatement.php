<?php

namespace Lomkit\Access\Decisions;

/** @internal */
final readonly class WriteStatement
{
    private const string NAME = '(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)';

    private const string TABLE = '(?<table>'.self::NAME.'(?:\.'.self::NAME.')*)';

    private const array ONE_TABLE = [
        '/^\s*(?:insert(?:\s+or\s+(?:ignore|replace))?(?:\s+ignore)?|replace)\s+into\s+'.self::TABLE.'\s*(?:\(|values\b|select\b|default\b)/i',
        '/^\s*update\s+'.self::TABLE.'\s+set\s/i',
        '/^\s*delete\s+from\s+'.self::TABLE.'\s*(?:where\b|$)/i',
    ];

    public static function reads(string $sql): bool
    {
        return preg_match('/^[\s(]*(?:select|show)\b/i', $sql) === 1;
    }

    public static function table(string $sql): ?string
    {
        if (str_contains($sql, ';')) {
            return null;
        }

        foreach (self::ONE_TABLE as $pattern) {
            if (preg_match($pattern, $sql, $matches) === 1) {
                return $matches['table'];
            }
        }

        return null;
    }
}

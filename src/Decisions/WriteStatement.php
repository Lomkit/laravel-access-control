<?php

namespace Lomkit\Access\Decisions;

/** @internal */
final readonly class WriteStatement
{
    private const string NAME = '(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)';

    private const string TABLE = '(?<table>'.self::NAME.'(?:\.'.self::NAME.')*)';

    private const string READ = '/^[\s(]*(?:select|show)\b/i';

    private const string SEVERAL_STATEMENTS = '/;\s*\S/';

    private const array ONE_TABLE = [
        '/^\s*(?:insert(?:\s+or\s+(?:ignore|replace))?(?:\s+ignore)?|replace)\s+into\s+'.self::TABLE.'\s*(?:\(|values\b|select\b|default\b)/i',
        '/^\s*update\s+'.self::TABLE.'\s+set\s(?!.*\bfrom\b)/is',
        '/^\s*delete\s+from\s+'.self::TABLE.'\s*(?:where\b|$)/i',
        '/^\s*merge\s+(?:into\s+)?'.self::TABLE.'(?:\s+with\s*\([^)]*\))?\s+using\b/i',
    ];

    public static function reads(string $sql): bool
    {
        return preg_match(self::READ, $sql) === 1 && ! self::holdsSeveralStatements($sql);
    }

    public static function table(string $sql): ?string
    {
        if (self::holdsSeveralStatements($sql)) {
            return null;
        }

        foreach (self::ONE_TABLE as $pattern) {
            if (preg_match($pattern, $sql, $matches) === 1) {
                return $matches['table'];
            }
        }

        return null;
    }

    private static function holdsSeveralStatements(string $sql): bool
    {
        return preg_match(self::SEVERAL_STATEMENTS, $sql) === 1;
    }
}

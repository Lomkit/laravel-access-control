<?php

namespace Lomkit\Access\Search;

/** @internal */
final class SearchKeyDigest
{
    public const string UNMATCHABLE = '#closed';

    private const string VERSION = 'lac1:';

    private const string ALGORITHM = 'sha256';

    private const int LENGTH = 32;

    public static function of(string $key): string
    {
        return substr(hash(self::ALGORITHM, self::VERSION.$key), 0, self::LENGTH);
    }

    public static function isDigest(string $value): bool
    {
        return preg_match(sprintf('/^[0-9a-f]{%d}\z/', self::LENGTH), $value) === 1;
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function all(array $keys): array
    {
        return array_map(self::of(...), $keys);
    }

    /**
     * @param  array<string, list<string>>  $document
     * @return array<string, list<string>>
     */
    public static function document(array $document): array
    {
        return array_map(self::all(...), $document);
    }
}

<?php

namespace Lomkit\Access\Search;

/** @internal */
final class SearchKeyDigest
{
    private const string VERSION = 'lac1:';

    private const string ALGORITHM = 'sha256';

    private const int LENGTH = 32;

    public static function of(string $key): string
    {
        return substr(hash(self::ALGORITHM, self::VERSION.$key), 0, self::LENGTH);
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

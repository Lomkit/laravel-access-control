<?php

namespace Lomkit\Access\Search\Parents;

use Lomkit\Access\Search\SearchKeyValues;

/** @internal */
final readonly class ParentKey
{
    public const string PREFIX = 'through';

    private const string SEPARATOR = '.';

    private function __construct(private string $prefix) {}

    public static function through(string $relation): self
    {
        return new self(self::PREFIX.self::SEPARATOR.SearchKeyValues::component($relation));
    }

    public function name(): string
    {
        return $this->prefix;
    }

    /**
     * @param  list<string>|null  $boundary
     * @param  list<string>|null  $access
     * @return list<string>
     */
    public function products(?array $boundary, ?array $access): array
    {
        $keys = [];

        foreach ($boundary ?? [null] as $boundaryKey) {
            foreach ($access ?? [null] as $accessKey) {
                $keys[] = $this->key($boundaryKey, $accessKey);
            }
        }

        return $keys;
    }

    private function key(?string $boundary, ?string $access): string
    {
        $components = [$this->prefix];

        if ($boundary === null) {
            $components[] = '0';
        } else {
            $components[] = (string) count(explode(self::SEPARATOR, $boundary));
            $components[] = $boundary;
        }

        if ($access !== null) {
            $components[] = $access;
        }

        return implode(self::SEPARATOR, $components);
    }
}

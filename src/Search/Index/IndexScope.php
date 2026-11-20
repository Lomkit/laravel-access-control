<?php

namespace Lomkit\Access\Search\Index;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Search\SearchField;
use Lomkit\Access\Search\SearchKeyDigest;
use Lomkit\Access\Search\SearchKeyValues;

/** @internal */
final readonly class IndexScope
{
    private const string MARKER = '@';

    private const string SEPARATOR = '.';

    private const string WHOLE_MODEL = '*';

    private function __construct(private string $prefix) {}

    /** @param  class-string<Model>  $model */
    public static function of(string $model): self
    {
        return new self(self::MARKER.SearchKeyValues::component((new $model)->getMorphClass()).self::SEPARATOR);
    }

    /**
     * @param  array<string, list<string>>  $keys
     * @return array<string, list<string>>
     */
    public function document(array $keys, bool $bounded): array
    {
        return [
            SearchField::Access->value => [...$this->digests($keys[SearchField::Access->value] ?? []), $this->wholeModel()],
            SearchField::Boundary->value => $bounded ? $this->digests($keys[SearchField::Boundary->value] ?? []) : [$this->wholeModel()],
        ];
    }

    /**
     * @param  list<string>|null  $access
     * @param  list<string>|null  $boundary
     * @return array<string, list<string>>
     */
    public function reader(?array $access, ?array $boundary): array
    {
        return [
            SearchField::Access->value => $access === null ? [$this->wholeModel()] : $this->digests($access),
            SearchField::Boundary->value => $boundary === null ? [$this->wholeModel()] : $this->digests($boundary),
        ];
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function digests(array $keys): array
    {
        return array_map(fn (string $key): string => SearchKeyDigest::of($this->prefix.$key), $keys);
    }

    private function wholeModel(): string
    {
        return SearchKeyDigest::of($this->prefix.self::WHOLE_MODEL);
    }
}

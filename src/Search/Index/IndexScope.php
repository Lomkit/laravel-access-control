<?php

namespace Lomkit\Access\Search\Index;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lomkit\Access\Exceptions\SharedMorphClassException;
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

    /**
     * @param  class-string<Model>  $model
     *
     * @throws SharedMorphClassException When the morph class of the model names another model.
     */
    public static function of(string $model): self
    {
        $morph = (new $model)->getMorphClass();
        $named = Relation::getMorphedModel($morph) ?? $morph;

        if ($named !== $model) {
            throw SharedMorphClassException::forIndex($model, $morph, $named);
        }

        return new self(self::MARKER.SearchKeyValues::component($morph).self::SEPARATOR);
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

<?php

namespace Lomkit\Access\Search\Index;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lomkit\Access\Exceptions\InvalidSearchRowException;

/** @internal */
final readonly class IndexRows
{
    /**
     * @param  list<Model>  $rows
     * @param  list<int|string>  $keys
     */
    private function __construct(
        private array $rows,
        private array $keys,
    ) {}

    /**
     * @param  iterable<mixed>  $rows
     *
     * @throws InvalidSearchRowException When a row is not an Eloquent model, is not of the model of the first row, is unsaved, deleted or soft-deleted, or has a key that is not an int or a string.
     */
    public static function of(iterable $rows): self
    {
        $list = [];
        $keys = [];

        foreach ($rows as $row) {
            if (! $row instanceof Model) {
                throw InvalidSearchRowException::notAModel($row);
            }

            if ($list !== [] && $row::class !== $list[0]::class) {
                throw InvalidSearchRowException::ofAnotherModel($list[0], $row);
            }

            $list[] = $row;
            $keys[] = self::keyOf($row);
        }

        return new self($list, $keys);
    }

    /** @throws InvalidSearchRowException When the row is unsaved, deleted or soft-deleted, or its key is not an int or a string. */
    private static function keyOf(Model $row): int|string
    {
        if (! $row->exists) {
            throw InvalidSearchRowException::unsavedOrDeleted($row);
        }

        if (in_array(SoftDeletes::class, class_uses_recursive($row), true) && method_exists($row, 'trashed') && $row->trashed() === true) {
            throw InvalidSearchRowException::trashed($row);
        }

        $key = $row->getKey();

        return is_int($key) || is_string($key) ? $key : throw InvalidSearchRowException::unsupportedKey($row, $key);
    }

    /** @return list<Model> */
    public function all(): array
    {
        return $this->rows;
    }

    /** @return list<int|string> */
    public function keys(): array
    {
        return $this->keys;
    }

    public function first(): ?Model
    {
        return $this->rows[0] ?? null;
    }
}

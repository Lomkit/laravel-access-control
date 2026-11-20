<?php

namespace Lomkit\Access\Search\Index;

use Illuminate\Database\Eloquent\Model;
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
     * @throws InvalidSearchRowException When a row is not an Eloquent model, is unsaved, or is not of the model of the first row.
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

            $key = $row->getKey();

            if (! $row->exists || (! is_int($key) && ! is_string($key))) {
                throw InvalidSearchRowException::unsaved($row);
            }

            $list[] = $row;
            $keys[] = $key;
        }

        return new self($list, $keys);
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

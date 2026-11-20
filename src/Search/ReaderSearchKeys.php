<?php

namespace Lomkit\Access\Search;

use Illuminate\Database\Eloquent\Model;

final readonly class ReaderSearchKeys
{
    /**
     * @param  class-string<Model>  $model
     * @param  array<string, list<string>>  $values
     */
    private function __construct(
        private string $model,
        private bool $closed,
        private array $values,
    ) {}

    /**
     * @internal
     *
     * @param  class-string<Model>  $model
     */
    public static function closed(string $model): self
    {
        $values = [];

        foreach (SearchField::names() as $field) {
            $values[$field] = [SearchKeyDigest::UNMATCHABLE];
        }

        return new self($model, true, $values);
    }

    /**
     * @internal
     *
     * @param  class-string<Model>  $model
     * @param  array<string, list<string>>  $values
     */
    public static function open(string $model, array $values): self
    {
        return new self($model, false, $values);
    }

    /** @return class-string<Model> */
    public function model(): string
    {
        return $this->model;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    /** @return array<string, list<string>> */
    public function values(): array
    {
        return $this->values;
    }
}

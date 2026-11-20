<?php

namespace Lomkit\Access\Search;

use Illuminate\Database\Eloquent\Model;

final readonly class ReaderSearchKeys
{
    /**
     * @param  class-string<Model>  $model
     * @param  array{_access: list<string>, _boundary: list<string>}  $values
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
        return new self($model, true, [
            SearchField::Access->value => [SearchKeyDigest::UNMATCHABLE],
            SearchField::Boundary->value => [SearchKeyDigest::UNMATCHABLE],
        ]);
    }

    /**
     * @internal
     *
     * @param  class-string<Model>  $model
     * @param  array{_access: list<string>, _boundary: list<string>}  $values
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

    /** @return array{_access: list<string>, _boundary: list<string>} */
    public function values(): array
    {
        return $this->values;
    }
}

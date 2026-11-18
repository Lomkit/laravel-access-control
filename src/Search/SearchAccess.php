<?php

namespace Lomkit\Access\Search;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lomkit\Access\Search\Freshness\ScoutOptions;
use Lomkit\Access\Search\Tokens\Syntax\FilterSyntax;

/** @internal */
final readonly class SearchAccess
{
    private function __construct(
        private SearchFilter $filter,
        private bool $hidesDeleted,
    ) {}

    public static function of(Model $model, SearchFilter $filter): self
    {
        return new self($filter, ScoutOptions::keepsSoftDeleted() && in_array(SoftDeletes::class, class_uses_recursive($model), true));
    }

    public function render(FilterSyntax $syntax): ?string
    {
        return $syntax->render($this->filter, $this->hidesDeleted);
    }
}

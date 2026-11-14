<?php

namespace Lomkit\Access\Testing;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;

final readonly class LevelNesting
{
    private const string UPPER_LEVEL_ALIAS = 'access_upper_level';

    private const int REPORTED_ROWS = 10;

    private AccessContext $context;

    public function __construct(
        private Control $control,
        Model $reader,
    ) {
        $this->context = AccessContext::of($reader, Ability::view());
    }

    /** @return list<int|string> */
    public function rowsMissingFrom(Perimeter $upper, Perimeter $lower): array
    {
        if ($upper->isUnrestricted()) {
            return [];
        }

        $model = $this->model();
        $lowerRows = $model->newQueryWithoutScopes();

        $this->control->declaration()->boundary()->applyScope($lowerRows, $this->context);
        $this->constrainTo($lowerRows, $lower);

        return $this->keys($lowerRows
            ->whereNotExists($this->upperRowsLike($model, $upper))
            ->limit(self::REPORTED_ROWS)
            ->pluck($model->getQualifiedKeyName())
            ->all());
    }

    /** @return Builder<Model> */
    private function upperRowsLike(Model $lowerRow, Perimeter $upper): Builder
    {
        $upperRow = $lowerRow->newInstance();
        $upperRows = $upperRow->newQueryWithoutScopes()->from($lowerRow->getTable().' as '.self::UPPER_LEVEL_ALIAS);

        $upperRow->setTable(self::UPPER_LEVEL_ALIAS);
        $upperRows->whereColumn($upperRow->getQualifiedKeyName(), $lowerRow->getQualifiedKeyName());
        $this->constrainTo($upperRows, $upper);

        return $upperRows;
    }

    /** @param  Builder<Model>  $query */
    private function constrainTo(Builder $query, Perimeter $level): void
    {
        if ($level->isUnrestricted()) {
            return;
        }

        $query->where(function (Builder $rows) use ($level): void {
            $level->applyQueryCallback($rows, $this->context);
        });
    }

    private function model(): Model
    {
        $model = $this->control->getModel();

        return new $model;
    }

    /**
     * @param  array<mixed>  $values
     * @return list<int|string>
     */
    private function keys(array $values): array
    {
        $keys = [];

        foreach ($values as $value) {
            if (is_int($value) || is_string($value)) {
                $keys[] = $value;
            }
        }

        return $keys;
    }
}

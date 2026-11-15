<?php

namespace Lomkit\Access\Integrations\RestApi;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Exceptions\InvalidAbilityException;
use Lomkit\Access\Exceptions\MissingControlException;
use Lomkit\Access\Perimeters\Parents\ParentStep;
use Lomkit\Rest\Authorization\RowVersions;

/** @internal */
final readonly class ParentVersions
{
    private const string PREFIX = 'parent_';

    private const string UNVERSIONED = 'parent_unversioned';

    /**
     * @throws MissingControlException When no control is registered for a parent model.
     * @throws InvalidAbilityException When a control cannot map the ability of the checks.
     */
    public function add(RowVersions $versions): void
    {
        foreach ($this->controlledGroups($versions) as $rows) {
            $control = Access::controlFor($rows[0]);
            $pairs = array_map(fn (Model $row): array => [$row, $row->getKey()], $rows);

            $this->walk($versions, $control, $control->abilityFor($versions->ability), $rows[0]->getKeyName(), $pairs, null);
        }
    }

    /** @return list<non-empty-list<Model>> */
    private function controlledGroups(RowVersions $versions): array
    {
        $groups = [];

        foreach ($versions->rows->all() as $row) {
            if (Access::hasControl($row)) {
                $groups[$row::class][] = $row;
            }
        }

        return array_values($groups);
    }

    /** @param  list<array{Model, mixed}>  $values */
    private function walk(RowVersions $versions, Control $control, Ability $ability, string $column, array $values, ?string $path): void
    {
        $steps = $control->declaration()->perimeters()->parentSteps($ability);

        if ($path === null && $steps === []) {
            return;
        }

        $pairs = $this->readable($versions, $values);
        $model = new ($control->getModel());
        $updatedAt = $path !== null && $model->usesTimestamps() ? $model->getUpdatedAtColumn() : null;
        $records = $this->records($model, $column, [$column, ...($updatedAt === null ? [] : [$updatedAt]), ...array_map(fn (ParentStep $step): string => $step->foreignKey, $steps)], $pairs);

        if ($path !== null) {
            $this->version($versions, $records, $updatedAt, $pairs, self::PREFIX.hash('xxh128', $path));
        }

        foreach ($steps as $step) {
            $next = $this->parentValues($records, $step, $pairs);

            if ($next !== []) {
                $this->walk($versions, $step->control, $step->ability, $step->ownerKey, $next, $path === null ? $step->relation : "{$path}.{$step->relation}");
            }
        }
    }

    /**
     * @param  list<array{Model, mixed}>  $values
     * @return list<array{Model, int|string}>
     */
    private function readable(RowVersions $versions, array $values): array
    {
        $pairs = [];

        foreach ($values as [$origin, $value]) {
            if (is_int($value) || (is_string($value) && $value !== '')) {
                $pairs[] = [$origin, $value];
            } else {
                $this->keepNoDecision($versions, $origin);
            }
        }

        return $pairs;
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array{Model, int|string}>  $pairs
     * @return array<string, Model>
     */
    private function records(Model $model, string $column, array $columns, array $pairs): array
    {
        $records = [];
        $rows = $model->newQueryWithoutScopes()
            ->whereIn($model->qualifyColumn($column), array_values(array_unique(array_column($pairs, 1))))
            ->get(array_map($model->qualifyColumn(...), array_values(array_unique($columns))));

        foreach ($rows as $row) {
            $value = $row->getAttributes()[$column] ?? null;

            if (is_int($value) || is_string($value)) {
                $records[(string) $value] = $row;
            }
        }

        return $records;
    }

    /**
     * @param  array<string, Model>  $records
     * @param  list<array{Model, int|string}>  $pairs
     */
    private function version(RowVersions $versions, array $records, ?string $updatedAt, array $pairs, string $key): void
    {
        foreach ($pairs as [$origin, $value]) {
            $record = $records[(string) $value] ?? null;

            if ($record === null) {
                continue;
            }

            $version = $updatedAt === null ? null : $record->getAttribute($updatedAt);

            if ($version instanceof DateTimeInterface) {
                $versions->add($origin, $key, $version);
            } else {
                $this->keepNoDecision($versions, $origin);
            }
        }
    }

    /**
     * @param  array<string, Model>  $records
     * @param  list<array{Model, int|string}>  $pairs
     * @return list<array{Model, mixed}>
     */
    private function parentValues(array $records, ParentStep $step, array $pairs): array
    {
        $next = [];

        foreach ($pairs as [$origin, $value]) {
            $parent = ($records[(string) $value] ?? null)?->getAttributes()[$step->foreignKey] ?? null;

            if ($parent !== null && $parent !== '') {
                $next[] = [$origin, $parent];
            }
        }

        return $next;
    }

    private function keepNoDecision(RowVersions $versions, Model $row): void
    {
        $versions->add($row, self::UNVERSIONED, 'unversioned');
        $versions->add($row, self::UNVERSIONED, 'unversioned again');
    }
}

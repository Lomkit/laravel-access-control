<?php

namespace Lomkit\Access\Decisions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Collection;
use Lomkit\Access\Ability;
use Lomkit\Access\Controls\Control;

/**
 * @internal
 *
 * @implements Scope<Model>
 */
final class AbilityColumns implements Scope
{
    private const string PREFIX = '__access_';

    private static int $sequence = 0;

    private readonly int $id;

    /** @var list<AbilityColumn> */
    private array $columns = [];

    /** @param  list<Ability>  $abilities */
    public function __construct(
        private readonly Control $control,
        private readonly Model $reader,
        private readonly array $abilities,
        private readonly DecisionMemo $memo,
    ) {
        $this->id = ++self::$sequence;
    }

    /** @param  Builder<covariant Model>  $builder */
    #[\Override]
    public function apply(Builder $builder, Model $model): void
    {
        $this->columns = [];

        if ($builder->getQuery()->columns === null) {
            $builder->select($model->qualifyColumn('*'));
        }

        $builder->addSelect("{$model->getQualifiedKeyName()} as {$this->keyAlias()}");

        foreach ($this->abilities as $position => $ability) {
            $recorder = VerdictRecorder::for($this->control, $this->reader, $ability, $this->memo);
            $case = $recorder->verdictCase($model);

            if ($case !== null) {
                $alias = self::PREFIX."{$this->id}_verdict_{$position}";
                $case->select($builder, $alias);
                $this->columns[] = new AbilityColumn($alias, $case, $recorder);
            }
        }
    }

    public function collect(mixed $result): mixed
    {
        if ($result instanceof Collection) {
            foreach ($result as $row) {
                if ($row instanceof Model) {
                    $this->collectFrom($row);
                }
            }
        }

        return $result;
    }

    private function collectFrom(Model $row): void
    {
        $attributes = $row->getAttributes();
        $key = $attributes[$this->keyAlias()] ?? null;

        foreach ($this->columns as $column) {
            $column->record($row, $key, $attributes);
        }

        $row->setRawAttributes(self::withoutReserved($row->getRawOriginal()), true);
        $row->setRawAttributes(self::withoutReserved($attributes));
    }

    private function keyAlias(): string
    {
        return self::PREFIX."{$this->id}_key";
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private static function withoutReserved(array $attributes): array
    {
        return array_filter($attributes, fn (string $name): bool => ! str_starts_with($name, self::PREFIX), ARRAY_FILTER_USE_KEY);
    }
}

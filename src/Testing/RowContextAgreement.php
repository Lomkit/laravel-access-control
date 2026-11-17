<?php

namespace Lomkit\Access\Testing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Decisions\RowContextSwitch;

/** @internal */
final readonly class RowContextAgreement
{
    /**
     * @param  list<Model>  $rows
     * @param  list<string>  $abilities
     */
    public function __construct(
        private Control $control,
        private string $column,
        private array $rows,
        private array $abilities,
    ) {}

    /** @return array{compared: int, disagreements: list<string>} */
    public function disagreementsFor(Model $reader): array
    {
        $switch = RowContextSwitch::current();
        $values = $switch->values();
        $compared = 0;
        $disagreements = [];

        foreach ($this->rows as $row) {
            $value = $values->match($row->getAttributes()[$this->column] ?? null);

            if ($value === null) {
                continue;
            }

            $compared++;
            $request = $this->answers($reader, $row);
            $switched = $switch->within($values, $value, fn (): array => $this->answers($reader, $row));

            foreach ($request as $path => $answer) {
                if ($switched[$path] !== $answer) {
                    $disagreements[] = sprintf('row [%s], %s: [%s] under the request, [%s] within [%s] alone', self::key($row), $path, $answer, $switched[$path], $value);
                }
            }
        }

        return ['compared' => $compared, 'disagreements' => $disagreements];
    }

    /** @return array<string, string> */
    private function answers(Model $reader, Model $row): array
    {
        Access::forgetDecisions();
        $answers = [];

        foreach ($this->abilities as $ability) {
            $answers["ability [{$ability}]"] = Gate::forUser($reader)->allows($ability, $row) ? 'granted' : 'refused';

            if (! $this->control->abilityFor($ability)->is(Ability::create())) {
                $query = $row->newQuery();
                $query->scopes(['controlledFor' => [$reader, $ability]]);
                $answers["controlled() for [{$ability}]"] = $query->whereKey($row->getKey())->exists() ? 'listed' : 'not listed';
            }
        }

        Access::forgetDecisions();

        return $answers;
    }

    private static function key(Model $row): string
    {
        $key = $row->getKey();

        return is_int($key) || is_string($key) ? (string) $key : get_debug_type($key);
    }
}

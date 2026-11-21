<?php

namespace Lomkit\Access\Testing;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Controls\Control;

/** @internal */
final class IndexKeysAgreement
{
    private const int REPORTED_ROWS = 10;

    /** @var array<int|string, array{_access: list<string>, _boundary: list<string>}> */
    private array $documents;

    /** @var array<string, Model> */
    private array $rows = [];

    public function __construct(private readonly Control $control)
    {
        $model = $this->control->getModel();
        $rows = (new $model)->newQuery()->get();

        $this->documents = Access::indexKeysOf($rows);

        foreach ($rows as $row) {
            $this->rows[self::key($row)] = $row;
        }
    }

    public function clockDrift(): ?ClockDrift
    {
        return (new ClockIndependence($this->control->searchRules(), $this->rows))->drift();
    }

    /** @return array{indexOnly: list<string>, sqlOnly: list<string>} */
    public function disagreementsFor(?Model $reader): array
    {
        $values = Access::indexReaderKeys($reader, $this->control->getModel())->values();
        $found = [];

        foreach ($this->documents as $key => $document) {
            if (self::matches($document, $values)) {
                $found[] = (string) $key;
            }
        }

        $selected = $this->selectedBy($reader);

        return [
            'indexOnly' => array_slice(array_values(array_diff($found, $selected)), 0, self::REPORTED_ROWS),
            'sqlOnly' => array_slice(array_values(array_diff($selected, $found)), 0, self::REPORTED_ROWS),
        ];
    }

    /**
     * @param  array{_access: list<string>, _boundary: list<string>}  $document
     * @param  array{_access: list<string>, _boundary: list<string>}  $values
     */
    private static function matches(array $document, array $values): bool
    {
        foreach ($values as $field => $fieldValues) {
            if (array_intersect($document[$field], $fieldValues) === []) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private function selectedBy(?Model $reader): array
    {
        $model = $this->control->getModel();
        $query = (new $model)->newQuery();

        $this->control->restrict($query, $reader, Ability::view());

        return array_values(array_map(
            fn (Model $row): string => self::key($row),
            array_filter($query->get()->all(), fn (Model $row): bool => array_key_exists(self::key($row), $this->documents)),
        ));
    }

    private static function key(Model $row): string
    {
        $key = $row->getKey();

        return is_int($key) || is_string($key) ? (string) $key : get_debug_type($key);
    }
}

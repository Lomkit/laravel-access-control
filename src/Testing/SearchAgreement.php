<?php

namespace Lomkit\Access\Testing;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Search\SearchDocuments;
use Lomkit\Access\Search\SearchField;
use Lomkit\Access\Search\SearchKeyDigest;

final class SearchAgreement
{
    private const int REPORTED_ROWS = 10;

    private const int REPORTED_KEYS = 10;

    /** @var array<string, array<string, list<string>>> */
    private array $keys = [];

    /** @var array<string, array<string, list<string>>> */
    private array $documents = [];

    public function __construct(private readonly Control $control)
    {
        $model = $this->control->getModel();
        $rows = array_values(array_filter(
            (new $model)->newQuery()->get()->all(),
            fn (Model $row): bool => ! method_exists($row, 'shouldBeSearchable') || $row->shouldBeSearchable() === true,
        ));

        foreach ((new SearchDocuments($this->control->searchRules()))->keysFor($rows) as $index => $keys) {
            $this->keys[self::key($rows[$index])] = $keys;
            $this->documents[self::key($rows[$index])] = SearchKeyDigest::document($keys);
        }
    }

    /** @return array{searchOnly: list<string>, sqlOnly: list<string>} */
    public function disagreementsFor(Model $reader): array
    {
        $filter = $this->control->searchFilter($reader);
        $searched = array_keys(array_filter($this->documents, $filter->matches(...)));
        $selected = array_values(array_intersect($this->selectedBy($reader), array_keys($this->documents)));

        return [
            'searchOnly' => $this->described(array_values(array_diff($searched, $selected))),
            'sqlOnly' => $this->described(array_values(array_diff($selected, $searched))),
        ];
    }

    public function readerKeys(Model $reader): string
    {
        $filter = $this->control->searchFilter($reader);

        if ($filter->isClosed()) {
            return 'none, the search is closed';
        }

        return $this->keysOf([SearchField::Boundary->value => $filter->boundaryKeys(), SearchField::Access->value => $filter->accessKeys()]);
    }

    /**
     * @param  list<string>  $rows
     * @return list<string>
     */
    private function described(array $rows): array
    {
        return array_map(
            fn (string $row): string => sprintf('%s with %s', $row, $this->keysOf($this->keys[$row])),
            array_slice($rows, 0, self::REPORTED_ROWS),
        );
    }

    /** @param  array<string, list<string>|null>  $keys */
    private function keysOf(array $keys): string
    {
        if (! $this->control->searchRules()->isBounded()) {
            unset($keys[SearchField::Boundary->value]);
        }

        $fields = [];

        foreach ($keys as $field => $values) {
            $fields[] = $values === null ? "any {$field}" : sprintf('%s {%s}', $field, self::listed($values));
        }

        return implode(' and ', $fields);
    }

    /** @param  list<string>  $values */
    private static function listed(array $values): string
    {
        $listed = implode(', ', array_slice($values, 0, self::REPORTED_KEYS));
        $more = count($values) - self::REPORTED_KEYS;

        return $more > 0 ? "{$listed} and {$more} more" : $listed;
    }

    /** @return list<string> */
    private function selectedBy(Model $reader): array
    {
        $model = $this->control->getModel();
        $query = (new $model)->newQuery();

        $this->control->restrict($query, $reader, Ability::view());

        return array_values(array_map(self::key(...), $query->get()->all()));
    }

    private static function key(Model $row): string
    {
        $key = $row->getKey();

        return is_int($key) || is_string($key) ? (string) $key : get_debug_type($key);
    }
}

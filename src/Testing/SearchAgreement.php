<?php

namespace Lomkit\Access\Testing;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Search\SearchDocuments;

final class SearchAgreement
{
    private const int REPORTED_ROWS = 10;

    /** @var array<string, array<string, list<string>|string>> */
    private array $documents = [];

    public function __construct(private readonly Control $control)
    {
        $model = $this->control->getModel();
        $rows = array_values(array_filter(
            (new $model)->newQuery()->get()->all(),
            fn (Model $row): bool => ! method_exists($row, 'shouldBeSearchable') || $row->shouldBeSearchable() === true,
        ));

        foreach ((new SearchDocuments($this->control->searchRules()))->metadataFor($rows) as $index => $document) {
            $this->documents[self::key($rows[$index])] = $document;
        }
    }

    /** @return array{searchOnly: list<string>, sqlOnly: list<string>} */
    public function disagreementsFor(Model $reader): array
    {
        $filter = $this->control->searchFilter($reader);
        $searched = array_keys(array_filter($this->documents, $filter->matches(...)));
        $selected = array_values(array_intersect($this->selectedBy($reader), array_keys($this->documents)));

        return [
            'searchOnly' => array_slice(array_values(array_diff($searched, $selected)), 0, self::REPORTED_ROWS),
            'sqlOnly' => array_slice(array_values(array_diff($selected, $searched)), 0, self::REPORTED_ROWS),
        ];
    }

    /** @return list<string> */
    private function selectedBy(Model $reader): array
    {
        $model = $this->control->getModel();
        $query = (new $model)->newQuery();

        $this->control->restrict($query, $reader);

        return array_values(array_map(self::key(...), $query->get()->all()));
    }

    private static function key(Model $row): string
    {
        $key = $row->getKey();

        return is_int($key) || is_string($key) ? (string) $key : get_debug_type($key);
    }
}

<?php

namespace Lomkit\Access\Testing;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Search\Parents\ParentKey;
use Lomkit\Access\Search\Parents\ParentSearch;
use Lomkit\Access\Search\SearchRules;

/** @internal */
final readonly class ClockDrift
{
    private const string DOCUMENTATION = 'https://github.com/Lomkit/laravel-access-control/blob/1.x/UPGRADE.md#rights-that-depend-on-the-date';

    private const string SEPARATOR = '.';

    /**
     * @param  list<string>  $rules
     * @param  array<string, class-string<Control>>  $parents
     */
    private function __construct(
        private string $row,
        private string $field,
        private array $rules,
        private array $parents,
        private int $days,
    ) {}

    /** @param  list<string>  $keys */
    public static function of(SearchRules $searchRules, string $row, string $field, array $keys, int $days): self
    {
        $parentSearches = [];

        foreach ($searchRules->parents() as $parent) {
            $parentSearches[$parent->name()] = $parent;
        }

        $rules = [];
        $parents = [];

        foreach ($keys as $key) {
            $components = explode(self::SEPARATOR, $key);
            $parent = $parentSearches[implode(self::SEPARATOR, array_slice($components, 0, 2))] ?? null;

            if ($components[0] === ParentKey::PREFIX && $parent instanceof ParentSearch) {
                $parents[$parent->relation()] = $parent->parent()::class;
            } else {
                $rules[] = $components[0];
            }
        }

        $rules = array_values(array_unique($rules));
        sort($rules);
        ksort($parents);

        return new self($row, $field, $rules, $parents, $days);
    }

    /**
     * @param  class-string<Control>  $control
     * @param  class-string  $model
     */
    public function message(string $control, string $model): string
    {
        return sprintf(
            'The keys of the [%s] control differ between two computations: the [%s] keys of the [%s] row with key [%s] are not the same when they are computed again with the clock moved %d days %s, %s. The keys of a row are computed when it is indexed and stay in the index until it is indexed again, so a row: closure must give the same keys at any instant. Read the clock in the reader: closure of searchKeys(), never in row:, and make row: give the same keys every time, such as by ordering a relation before reading its first(). See "Rights that depend on the date" at %s.',
            $control,
            $this->field,
            $model,
            $this->row,
            abs($this->days),
            $this->days < 0 ? 'back' : 'forward',
            $this->origin(),
            self::DOCUMENTATION,
        );
    }

    private function origin(): string
    {
        $origins = $this->rules === [] ? [] : [sprintf('in the keys of the rules [%s]', implode(', ', $this->rules))];

        foreach ($this->parents as $relation => $parent) {
            $origins[] = sprintf('in the keys inherited through the [%s] relation, which the row: closures and the boundary of the [%s] control, or of the parents it reads through, compute', $relation, $parent);
        }

        return implode(' and ', $origins);
    }
}

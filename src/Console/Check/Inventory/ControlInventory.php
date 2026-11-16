<?php

namespace Lomkit\Access\Console\Check\Inventory;

use Lomkit\Access\Boundaries\NoBoundary;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Search\UsesControlledSearchable;

/** @internal */
final readonly class ControlInventory
{
    /** @param  list<PerimeterInventory>  $perimeters */
    private function __construct(
        public string $control,
        public string $model,
        public array $perimeters,
        public ?string $boundary,
        public ?CheckMode $boundaryMode,
        public bool $boundarySearchable,
        public bool $searchable,
        public ?string $engine,
        public PolicyInventory $policy,
    ) {}

    public static function of(Control $control, ?string $engine, PolicyInventory $policy): self
    {
        $declaration = $control->declaration();
        $boundary = $declaration->boundary();
        $bounded = ! $boundary instanceof NoBoundary;

        return new self(
            $control::class,
            $control->getModel(),
            self::perimeters($control),
            $bounded ? $boundary::class : null,
            $bounded ? CheckMode::of($boundary->checksInMemory()) : null,
            $declaration->search()->isSearchable(),
            UsesControlledSearchable::check($control->getModel()),
            $engine,
            $policy,
        );
    }

    /** @return list<PerimeterInventory> */
    private static function perimeters(Control $control): array
    {
        $perimeters = [];

        foreach ($control->declaration()->perimeters()->entries() as $index => $entry) {
            $position = '#'.($index + 1);

            if (! $entry instanceof Levels) {
                $perimeters[] = PerimeterInventory::of($entry, $position);

                continue;
            }

            foreach ($entry->all() as $level => $perimeter) {
                $perimeters[] = PerimeterInventory::of($perimeter, "{$position}.".($level + 1));
            }
        }

        return $perimeters;
    }

    public function boundarySummary(): string
    {
        if ($this->boundary === null || $this->boundaryMode === null) {
            return 'none';
        }

        return "{$this->boundary}, {$this->boundaryMode->value}".($this->boundarySearchable ? ', searchable' : ', no search form');
    }

    public function searchSummary(): string
    {
        if (! $this->searchable) {
            return 'not searchable';
        }

        return 'ControlledSearchable on '.($this->engine ?? 'an engine that cannot be built');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'control' => $this->control,
            'model' => $this->model,
            'perimeters' => array_map(fn (PerimeterInventory $perimeter): array => $perimeter->toArray(), $this->perimeters),
            'boundary' => $this->boundary === null ? null : ['boundary' => $this->boundary, 'mode' => $this->boundaryMode?->value, 'searchable' => $this->boundarySearchable],
            'search' => ['searchable' => $this->searchable, 'engine' => $this->engine],
            'policy' => $this->policy->toArray(),
        ];
    }
}

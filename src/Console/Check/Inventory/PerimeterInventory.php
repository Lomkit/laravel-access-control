<?php

namespace Lomkit\Access\Console\Check\Inventory;

use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Perimeters\ThroughParent;

/** @internal */
final readonly class PerimeterInventory
{
    /** @param  array<string, string>  $delegates */
    private function __construct(
        public string $position,
        public string $perimeter,
        public CheckMode $mode,
        public bool $unrestricted,
        public ?string $searchKeys,
        public ?string $relation,
        public array $delegates,
    ) {}

    public static function of(Perimeter $perimeter, string $position): self
    {
        $delegation = $perimeter instanceof ThroughParent ? $perimeter->delegation() : null;

        return new self(
            $position,
            $perimeter::class,
            CheckMode::of($perimeter->checksInMemory()),
            $perimeter->isUnrestricted(),
            $perimeter->declaredSearchKeys()?->name(),
            $delegation?->relation(),
            $delegation?->names() ?? [],
        );
    }

    public function label(): string
    {
        $name = class_basename($this->perimeter);

        return $this->relation === null ? "{$this->position} {$name}" : "{$this->position} {$name} [{$this->relation}]";
    }

    public function summary(): string
    {
        $parts = [$this->mode->value];

        if ($this->unrestricted) {
            $parts[] = 'unrestricted';
        }

        if ($this->searchKeys !== null) {
            $parts[] = "search keys {$this->searchKeys}";
        }

        foreach ($this->delegates as $child => $parent) {
            $parts[] = "{$child} from the parent's {$parent}";
        }

        return implode(', ', $parts);
    }

    /** @return array{position: string, perimeter: string, mode: string, unrestricted: bool, search_keys: string|null, parent: array{relation: string, delegates: array<string, string>}|null} */
    public function toArray(): array
    {
        return [
            'position' => $this->position,
            'perimeter' => $this->perimeter,
            'mode' => $this->mode->value,
            'unrestricted' => $this->unrestricted,
            'search_keys' => $this->searchKeys,
            'parent' => $this->relation === null ? null : ['relation' => $this->relation, 'delegates' => $this->delegates],
        ];
    }
}

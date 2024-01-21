<?php

namespace Lomkit\Access\Controls;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lomkit\Access\Controls\Concerns\HasPolicy;
use Lomkit\Access\Controls\Concerns\HasQuery;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Perimeters\Perimeters;

class Control
{
    use HasQuery, HasPolicy;

    protected Perimeters $perimeters;

    protected Collection $concernedPerimeters;

    public function __construct(Perimeters $perimeters)
    {
        $this->perimeters = $perimeters;
    }

    public function should(Perimeter $perimeter): bool {
        $perimeterMethod = 'should'.Str::studly($perimeter->name);

        if (method_exists($this, $perimeterMethod)) {
            return $this->$perimeterMethod();
        }

        return false;
    }

    public function getConcernedPerimeters(): Collection {
        if (isset($this->concernedPerimeters)) {
            return $this->concernedPerimeters;
        }

        $perimeters = new Collection();

        foreach ($this->perimeters->getPerimeters() as $perimeter) {
            if ($this->should($perimeter)) {
                $perimeters->push($perimeter);
            }
        }

        return $this->concernedPerimeters = $perimeters;
    }
}
<?php

namespace Lomkit\Access\Controls;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Lomkit\Access\Controls\Concerns\HasQuery;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Perimeters\Perimeters;

class Control
{
    use HasQuery;

    protected Perimeters $perimeters;

    public function __construct(Perimeters $perimeters)
    {
        $this->perimeters = $perimeters;
    }

    protected function should(Perimeter $perimeter, Request $request): bool {
        $perimeterMethod = 'sould'.Str::studly($perimeter->name);

        if (method_exists($this, $perimeterMethod)) {
            return $this->$perimeterMethod($request);
        }

        return false;
    }
}
<?php

namespace Lomkit\Access\Controls;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Lomkit\Access\Controls\Concerns\HasQuery;
use Lomkit\Access\Perimeters\Perimeters;

class Control
{
    use HasQuery;

    protected Perimeters $perimeters;

    public function __construct(Perimeters $perimeters)
    {
        $this->perimeters = $perimeters;
    }

    public function should(Request $request, string $name): bool {
        $perimeter = $this->perimeters->findPerimeter($name);

        $perimeterMethod = 'sould'.Str::studly($perimeter->name);

        if (method_exists($this, $perimeterMethod)) {
            return $this->$perimeterMethod($request);
        }

        return false;
    }
}
<?php

namespace Lomkit\Access\Tests\Support\Access\Controls;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Support\Access\Perimeters\ClientPerimeter;
use Lomkit\Access\Tests\Support\Access\Perimeters\GlobalPerimeter;
use Lomkit\Access\Tests\Support\Access\Perimeters\OwnPerimeter;

class ModelControl extends Control
{
    protected function perimeters(): array
    {
        return [
            GlobalPerimeter::new()
                ->query(function (Builder $query) {
                    $query->where('is_global', true);
                }),
            ClientPerimeter::new()
                ->query(function (Builder $query) {
                    $query->where('is_client', true);
                }),
            OwnPerimeter::new()
                ->query(function (Builder $query) {
                    $query->where('is_own', true);
                }),
            ];
    }
}
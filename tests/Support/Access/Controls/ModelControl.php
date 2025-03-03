<?php

namespace Lomkit\Access\Tests\Support\Access\Controls;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Support\Access\Perimeters\ClientPerimeter;
use Lomkit\Access\Tests\Support\Access\Perimeters\GlobalPerimeter;
use Lomkit\Access\Tests\Support\Access\Perimeters\OwnPerimeter;
use Lomkit\Access\Tests\Support\Access\Perimeters\SharedPerimeter;

class ModelControl extends Control
{
    protected function perimeters(): array
    {
        // @TODO: possible to extract the should callback to another method ??
        $shouldCallback = function (Model $user, string $method, Model $model) {
            return in_array($method, explode(',', $model->allowed_methods));
        };

        // @TODO: should or applies ? Why do we have two way of defining ?

        return [
            SharedPerimeter::new()
                ->should($shouldCallback)
                ->query(function (Builder $query, Model $user) {
                    return $query->where('is_shared', true);
                }),
            GlobalPerimeter::new()
                ->should($shouldCallback)
                ->query(function (Builder $query, Model $user) {
                    return $query->where('is_global', true);
                }),
            ClientPerimeter::new()
                ->should($shouldCallback)
                ->query(function (Builder $query, Model $user) {
                    return $query->where('is_client', true);
                }),
            OwnPerimeter::new()
                ->should($shouldCallback)
                ->query(function (Builder $query, Model $user) {
                    $query->where('is_own', true);
                }),
        ];
    }
}

<?php

namespace Lomkit\Access\Tests\Support\Access\Controls;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
                ->should(function (Model $user, string $method, Model $model) {
                    return str_contains($model->allowed_methods, $method);
                })
                ->query(function (Builder $query) {
                    $query->where('is_global', true);
                }),
            ClientPerimeter::new()
                ->should(function (Model $user, string $method, Model $model) {
                    return str_contains($model->allowed_methods, $method);
                })
                ->query(function (Builder $query) {
                    $query->where('is_client', true);
                }),
            OwnPerimeter::new()
                ->should(function (Model $user, string $method, Model $model) {
                    return str_contains($model->allowed_methods, $method);
                })
                ->query(function (Builder $query) {
                    $query->where('is_own', true);
                }),
        ];
    }
}

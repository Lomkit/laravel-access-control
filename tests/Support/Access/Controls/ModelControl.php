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

        return [
            SharedPerimeter::new()
                ->should($shouldCallback)
                ->allowed(function(Model $user) {
                    return $user->should_shared;
                })
                ->query(function (Builder $query, Model $user) {
                    return $query->orWhere('is_shared', true);
                }),
            GlobalPerimeter::new()
                ->should($shouldCallback)
                ->allowed(function(Model $user) {
                    return $user->should_global;
                })
                ->query(function (Builder $query, Model $user) {
                    return $query->orWhere('is_global', true);
                }),
            ClientPerimeter::new()
                ->should($shouldCallback)
                ->allowed(function(Model $user) {
                    return $user->should_client;
                })
                ->query(function (Builder $query, Model $user) {
                    return $query->orWhere('is_client', true);
                }),
            OwnPerimeter::new()
                ->should($shouldCallback)
                ->allowed(function(Model $user) {
                    return $user->should_own;
                })
                ->query(function (Builder $query, Model $user) {
                    return $query->orWhere('is_own', true);
                }),
        ];
    }
}

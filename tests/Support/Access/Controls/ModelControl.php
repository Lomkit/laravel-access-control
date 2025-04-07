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
    /**
     * Constructs and returns an array of perimeter configurations for model access control.
     *
     * Each perimeter instance—Shared, Global, Client, and Own—is configured with:
     * - An "allowed" callback that checks if the user possesses the corresponding permission.
     * - A "should" callback that validates whether a specific access method is permitted, with
     *   the Shared perimeter appending '_shared' to the method name.
     * - A "query" callback that augments a query builder with a condition to filter models
     *   based on the respective access flag.
     *
     * @return array An array of configured perimeter instances.
     */
    protected function perimeters(): array
    {
        // @TODO: possible to extract the should callback to another method ??
        $shouldCallback = function (Model $user, string $method, Model $model) {
            return in_array($method, explode(',', $model->allowed_methods));
        };

        return [
            SharedPerimeter::new()
                ->allowed(function (Model $user) {
                    return $user->should_shared;
                })
                ->should(function (Model $user, string $method, Model $model) {
                    return in_array($method.'_shared', explode(',', $model->allowed_methods));
                })
                ->query(function (Builder $query, Model $user) {
                    return $query->orWhere('is_shared', true);
                }),
            GlobalPerimeter::new()
                ->allowed(function (Model $user) {
                    return $user->should_global;
                })
                ->should($shouldCallback)
                ->query(function (Builder $query, Model $user) {
                    return $query->orWhere('is_global', true);
                }),
            ClientPerimeter::new()
                ->allowed(function (Model $user) {
                    return $user->should_client;
                })
                ->should($shouldCallback)
                ->query(function (Builder $query, Model $user) {
                    return $query->orWhere('is_client', true);
                }),
            OwnPerimeter::new()
                ->allowed(function (Model $user) {
                    return $user->should_own;
                })
                ->should($shouldCallback)
                ->query(function (Builder $query, Model $user) {
                    return $query->orWhere('is_own', true);
                }),
        ];
    }
}

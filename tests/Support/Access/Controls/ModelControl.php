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
     * Returns an array of configured perimeter instances for access control.
     *
     * The method constructs four perimeter configurations (Shared, Global, Client, and Own) that determine
     * if a user can access a model based on user permissions and the model's allowed methods attribute.
     * Each perimeter is set up with:
     * - an "allowed" callback to check the user's permission flag,
     * - a "should" callback that validates if the method is enabled for the model (using a custom check for SharedPerimeter and a common callback for others),
     * - a "query" callback to modify the query builder with a condition specific to the perimeter type.
     *
     * @return array An array of perimeter instances with pre-defined access control configurations.
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

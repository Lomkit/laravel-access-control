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
     * Returns an array of configured perimeter instances for model access control.
     *
     * Each perimeter (Shared, Global, Client, and Own) is set up with:
     * - A permission check (allowed callback) to determine if the user is authorized.
     * - A method availability check (should callback) to verify if the action is permitted based on the model's allowed methods.
     * - A query modifier (query callback) to apply a specific filtering condition.
     *
     * @return array An array of perimeter configuration instances.
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

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
     * Defines and returns a set of access perimeters with associated permission and query logic.
     *
     * Each perimeter specifies conditions for access (allowed, should) and query modifications for both Eloquent and Laravel Scout builders, based on user and model attributes.
     *
     * @return array List of configured perimeter instances for access control.
     */
    protected function perimeters(): array
    {
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
                ->scoutQuery(function (\Laravel\Scout\Builder $query, Model $user) {
                    return $query->where('is_shared', true);
                })
                ->query(function (Builder $query, Model $user) {
                    return $query->orWhere('is_shared', true);
                }),
            GlobalPerimeter::new()
                ->allowed(function (Model $user) {
                    return $user->should_global;
                })
                ->should($shouldCallback)
                ->scoutQuery(function (\Laravel\Scout\Builder $query, Model $user) {
                    return $query->where('is_global', true);
                })
                ->query(function (Builder $query, Model $user) {
                    return $query->orWhere('is_global', true);
                }),
            ClientPerimeter::new()
                ->allowed(function (Model $user) {
                    return $user->should_client;
                })
                ->should($shouldCallback)
                ->scoutQuery(function (\Laravel\Scout\Builder $query, Model $user) {
                    return $query->where('is_client', true);
                })
                ->query(function (Builder $query, Model $user) {
                    return $query->orWhere('is_client', true);
                }),
            OwnPerimeter::new()
                ->allowed(function (Model $user) {
                    return $user->should_own;
                })
                ->should($shouldCallback)
                ->scoutQuery(function (\Laravel\Scout\Builder $query, Model $user) {
                    return $query->where('is_own', true);
                })
                ->query(function (Builder $query, Model $user) {
                    return $query->orWhere('is_own', true);
                }),
        ];
    }
}

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
        return [
            SharedPerimeter::new()
                ->allowed(function (Model $user, string $method) {
                    return $user->can(sprintf('%s shared models', $method));
                })
                ->should(function (Model $user, Model $model) {
                    return $model->sharedWithUsers()->where('id', $user->getKey())->exists();
                })
                ->scoutQuery(function (\Laravel\Scout\Builder $query, Model $user) {
                    return $query->where('shared_with_users', $user->getKey());
                })
                ->query(function (Builder $query, Model $user) {
                    return $query->orWhereHas('sharedWithUsers', function (Builder $query) use ($user) {
                        return $query->where('id', $user->getKey());
                    });
                }),
            GlobalPerimeter::new()
                ->allowed(function (Model $user, string $method) {
                    return $user->can(sprintf('%s global models', $method));
                })
                ->should(function (Model $user, Model $model) {
                    return true;
                })
                ->scoutQuery(function (\Laravel\Scout\Builder $query, Model $user) {
                    return $query;
                })
                ->query(function (Builder $query, Model $user) {
                    return $query;
                }),
            ClientPerimeter::new()
                ->allowed(function (Model $user, string $method) {
                    return $user->can(sprintf('%s client models', $method));
                })
                ->should(function (Model $user, Model $model) {
                    return $model->client()->is($user->client);
                })
                ->scoutQuery(function (\Laravel\Scout\Builder $query, Model $user) {
                    return $query->where('client_id', $user->client->getKey());
                })
                ->query(function (Builder $query, Model $user) {
                    return $query->orWhere('client_id', $user->client->getKey());
                }),
            OwnPerimeter::new()
                ->allowed(function (Model $user, string $method) {
                    return $user->can(sprintf('%s own models', $method));
                })
                ->should(function (Model $user, Model $model) {
                    return $model->user()->is($user);
                })
                ->scoutQuery(function (\Laravel\Scout\Builder $query, Model $user) {
                    return $query->where('author_id', $user->getKey());
                })
                ->query(function (Builder $query, Model $user) {
                    return $query->orWhere('author_id', $user->getKey());
                }),
        ];
    }
}

<?php

namespace Lomkit\Access\Tests\Support\Access\Controls;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Support\Access\Perimeters\OwnPerimeter;
use Lomkit\Access\Tests\Support\Models\ControlledUser;

class ControlledUserControl extends Control
{
    protected string $model = ControlledUser::class;

    protected function perimeters(): array
    {
        return [
            OwnPerimeter::new()
                ->allowed(function (Model $user, string $method) {
                    return true;
                })
                ->should(function (Model $user, Model $model) {
                    return $model->is($user);
                })
                ->scoutQuery(function (\Laravel\Scout\Builder $query, Model $user) {
                    return $query->where('id', $user->getKey());
                })
                ->query(function (Builder $query, Model $user) {
                    return $query->where('id', $user->getKey());
                }),
        ];
    }
}
